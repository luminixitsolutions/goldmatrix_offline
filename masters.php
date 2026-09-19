<?php session_start();
require_once 'config.php';
if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/includes/auragold_set_software_page_guard.php';
auragold_set_software_page_guard();
auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
require_once __DIR__ . '/includes/auragold_carat_dashboard_image_schema.php';
require_once __DIR__ . '/includes/auragold_carat_purity_for_schema.php';
require_once __DIR__ . '/includes/auragold_metal_dashboard_image_schema.php';
require_once __DIR__ . '/includes/auragold_metal_opening_fields_schema.php';
if (isset($conn) && $conn) {
    auragold_ensure_tbl_carat_dashboard_images($conn);
    auragold_ensure_tbl_carat_purity_split($conn);
    auragold_ensure_tbl_metal_dashboard_images($conn);
    auragold_ensure_tbl_metal_opening_fields($conn);
}
$masters_metal_has_dash_img = isset($conn) && $conn && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal', 'dashboard_image_path');
$masters_metal_show_dash = isset($conn) && $conn && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal', 'show_on_dashboard');
$masters_metal_has_opening_fields = isset($conn) && $conn && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal', 'product_opening_name');
$masters_metal_table_cols = 4 + ($masters_metal_has_opening_fields ? 2 : 0) + ($masters_metal_has_dash_img ? 1 : 0) + ($masters_metal_show_dash ? 1 : 0);
$masters_metal_list_order = $masters_metal_has_opening_fields ? ' ORDER BY order_no ASC, id DESC' : ' ORDER BY id DESC';
if (!function_exists('masters_req')) {
    function masters_req() {
        return ' <span class="text-danger">*</span>';
    }
}
?>
<!DOCTYPE html>

<html lang="en" class="default-style">

<head>
    <title>Gold Matrix - Advance Software for Smart Jewellers</title>

<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">

<meta name="description" content="Gold Matrix is an advanced jewellery management software designed for smart jewellers. Manage billing, inventory, karigar accounts, gold rates, stock tracking, CRM, reports, and financial operations with precision and ease." />

<meta name="keywords" content="Jewellery Software, Gold Billing Software, Jewellery Management System, Gold Shop Software, Jewellery Inventory Software, Karigar Management, Gold Rate Management, Retail Jewellery Software, Jewellery ERP, Smart Jewellers Software" />

<meta name="author" content="Gold Matrix Software Team" />
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
<link rel="stylesheet" href="set-software-sidebar.css">
</head>

<style>
/* ===== Masters UI (Jewelsteps Style) ===== */
.masters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}

.master-card {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
}

.master-card:hover {
    border-color: #c5a864;
    box-shadow: 0 4px 14px rgba(197,168,100,0.25);
}

.master-header {
    padding: 10px 14px;
    font-weight: 700;
    font-size: 0.85rem;
    color: #11294b;
    background: linear-gradient(135deg,#f8fafc,#f1f5f9);
    border-bottom: 2px solid #c5a864;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.master-body {
    padding: 12px;
    flex: 1;
    overflow-y: auto;
    max-height: 220px;
}

.master-body table {
    font-size: 0.78rem;
}

.master-body table th {
    font-weight: 700;
    color: #ffffff;
    background: #f8fafc;
}

.master-footer {
    padding: 10px 12px;
    border-top: 1px dashed #e2e8f0;
    text-align: center;
}

.master-add-btn {
    width: 100%;
    border: 1.5px dashed #c5a864;
    background: rgba(197,168,100,0.07);
    color: #8b6f3a;
    font-weight: 600;
    font-size: 0.8rem;
    padding: 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.25s ease;
}

.master-add-btn:hover {
    background: linear-gradient(135deg,#c5a864,#a68a4a);
    color: #fff;
    border-color: #a68a4a;
}
/* ===== Jewelsteps-style Internal Scroll ===== */
.master-scroll {
    max-height: 120px;          /* adjust if needed */
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 4px;
}

/* Scrollbar (Chrome / Edge / Brave) */
.master-scroll::-webkit-scrollbar {
    width: 6px;
}

.master-scroll::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 10px;
}

.master-scroll::-webkit-scrollbar-thumb {
    background: #c5a864;
    border-radius: 10px;
}

.master-scroll::-webkit-scrollbar-thumb:hover {
    background: #a68a4a;
}

/* Firefox */
.master-scroll {
    scrollbar-width: thin;
    scrollbar-color: #c5a864 #f1f5f9;
     max-height: 260px;
    overflow-y: auto;
    overflow-x: auto;
}


.master-scroll table{
    white-space: nowrap;
}

.master-body table th .text-danger,
.modal-body label .text-danger {
    font-weight: 700;
}



/* ===== AJAX Loader ===== */
#ajaxLoader {
    position: fixed;
    inset: 0;
    z-index: 9999;
}

.loader-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.25);
}

.loader-spinner {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 45px;
    height: 45px;
    margin: -22px 0 0 -22px;
    border: 4px solid #e5e7eb;
    border-top: 4px solid #c5a864;
    border-radius: 50%;
    animation: spin 0.9s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.masters-search-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
}

.masters-search-wrap {
    position: relative;
    width: 100%;
    max-width: 360px;
    min-width: 220px;
}

.masters-search-wrap i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #64748b;
    pointer-events: none;
}

.masters-search-wrap input {
    width: 100%;
    height: 40px;
    padding: 8px 12px 8px 38px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 0.85rem;
    box-sizing: border-box;
    background: #fff;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.masters-search-wrap input:focus {
    outline: none;
    border-color: #c5a864;
    box-shadow: 0 0 0 3px rgba(197, 168, 100, 0.18);
}

.masters-search-hint {
    font-size: 0.78rem;
    color: #64748b;
}

.masters-no-results {
    display: none;
    padding: 28px 16px;
    text-align: center;
    color: #94a3b8;
    font-size: 0.9rem;
    border: 1.5px dashed #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}

.masters-no-results.show {
    display: block;
}

.master-card.masters-hidden {
    display: none !important;
}
</style>

</head>

<body>
<!-- [ Preloader ] Start -->
<div class="page-loader">
    <div class="bg-primary"></div>
</div>
<!-- [ Preloader ] End -->

<!-- [ Layout wrapper ] Start -->
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <!-- [ Layout sidenav ] Start -->
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark">
            <!-- Brand demo -->
            <div class="app-brand demo">
                <span class="app-brand-logo demo">
                    <img src="assets/img/logo.png" alt="Brand Logo" class="img-fluid">
                </span>
                <a href="index-2.html" class="app-brand-text demo sidenav-text font-weight-normal ml-2">Empire</a>
                <a href="javascript:" class="layout-sidenav-toggle sidenav-link text-large ml-auto">
                    <i class="ion ion-md-menu align-middle"></i>
                </a>
            </div>
            <div class="sidenav-divider mt-0"></div>

            <!-- Links -->
            <ul class="sidenav-inner py-1">
                <li class="sidenav-item active">
                    <a href="billing-sales-invoice.html" class="sidenav-link">
                        <i class="sidenav-icon feather icon-file-text"></i>
                        <div>Sales Invoice</div>
                    </a>
                </li>
            </ul>
        </div>
        <!-- [ Layout sidenav ] End -->

        <!-- [ Layout container ] Start -->
        <div class="layout-container">
            <!-- [ Layout navbar ( Header ) ] Start -->
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar">
                <a href="index-2.html" class="navbar-brand app-brand demo d-lg-none py-0 mr-4">
                    <span class="app-brand-logo demo">
                        <img src="assets/img/logo-dark.png" alt="Brand Logo" class="img-fluid">
                    </span>
                    <span class="app-brand-text demo font-weight-normal ml-2">Empire</span>
                </a>

                <div class="layout-sidenav-toggle navbar-nav d-lg-none align-items-lg-center mr-auto">
                    <a class="nav-item nav-link px-0 mr-lg-4" href="javascript:">
                        <i class="ion ion-md-menu text-large align-middle"></i>
                    </a>
                </div>

                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#layout-navbar-collapse">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="navbar-collapse collapse" id="layout-navbar-collapse">
                    <div class="navbar-nav align-items-lg-center ml-auto">
                        <div class="demo-navbar-notifications nav-item dropdown mr-lg-3">
                            <a class="nav-link dropdown-toggle hide-arrow" href="#" data-toggle="dropdown">
                                <i class="feather icon-bell navbar-icon align-middle"></i>
                                <span class="badge badge-danger badge-dot indicator"></span>
                            </a>
                        </div>
                        <div class="demo-navbar-user nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                                <span class="d-inline-flex flex-lg-row-reverse align-items-center align-middle">
                                    <img src="assets/img/avatars/1.png" alt class="d-block ui-w-30 rounded-circle">
                                    <span class="px-1 mr-lg-2 ml-2 ml-lg-0">SUPER ADMIN</span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
            <!-- [ Layout navbar ( Header ) ] End -->

            <!-- [ Layout content ] Start -->
            <div class="layout-content">
                <!-- [ content ] Start -->
                <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                    <?php include 'sidebar.php';?>

                    <div class="set-software-wrapper">
                        <?php include 'set-software-sidebar.php'; ?>
                        <div class="set-software-main">
                    <?php include __DIR__ . '/includes/set-software-masters-tabs.php'; ?>
                    <div class="row">
                        <!-- Main Content Area -->
                        <div class="col-lg-12" >
                            <!-- Transaction Details Form -->
                           <div class="card mb-4">
    <div class="card-body">

        <div class="masters-search-bar">
            <div class="masters-search-wrap">
                <i class="feather icon-search"></i>
                <input type="search" id="mastersSearchInput" placeholder="Search master heading (e.g. Location, Carat, Metal)..." autocomplete="off">
            </div>
            <span class="masters-search-hint" id="mastersSearchCount"></span>
        </div>

        <div class="masters-no-results" id="mastersNoResults">
            No master found for this heading. Try another name.
        </div>

        <div class="masters-grid" id="mastersGrid">

            <!-- LOCATION -->
           <div class="master-card">
    <div class="master-header">Location</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:70px;">Action</th>
                    </tr>
                </thead>

                <tbody id="locationTableBody">
                <?php
                $sql = "SELECT id, name FROM tbl_location WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_location') . " ORDER BY id DESC";
                $locations = getList($sql);

                if(count($locations) > 0){
                    foreach($locations as $row){
                ?>
                    <tr id="location_<?php echo $row['id']; ?>">
                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editLocation(<?php echo $row['id']; ?>,'<?php echo htmlspecialchars($row['name'],ENT_QUOTES); ?>')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteLocation(<?php echo $row['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php
                    }
                } else {
                    echo '<tr id="noLocationRow"><td colspan="2" class="text-center text-muted">No Location Found</td></tr>';
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" data-toggle="modal" data-target="#locationModal">
            + Add Location
        </button>
    </div>
</div>

            <!-- TAX MASTER -->
            <?php
            $tax_master_list = [];
            $tax_master_table_exists = @mysqli_query($conn, "SELECT 1 FROM tbl_tax_master LIMIT 1");
            if ($tax_master_table_exists && $tax_master_table_exists !== false) {
                $tax_master_list = getList("SELECT id, name, default_value, default_calculation_mode, gst_supply_scope, sort_order FROM tbl_tax_master WHERE status = 1 " . auragold_master_list_sql_suffix($conn, 'tbl_tax_master') . " ORDER BY sort_order ASC, id ASC");
            }
            $calculation_modes_master = getList("SELECT id, name FROM tbl_calculation_modes WHERE status = 1 ORDER BY sort_order ASC, name ASC");
            ?>
            <div class="master-card">
                <div class="master-header">Tax Master</div>
                <div class="master-body">
                    <div class="master-scroll">
                        <table class="table table-sm table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Name<?php echo masters_req(); ?></th>
                                    <th>Default Value</th>
                                    <th>Calculation Mode</th>
                                    <th>GST supply</th>
                                    <th style="width:90px;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="taxMasterTableBody">
                                <?php
                                if (!empty($tax_master_list)) {
                                    foreach ($tax_master_list as $r) {
                                        ?>
                                        <tr id="taxMaster_<?php echo $r['id']; ?>">
                                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                                            <td><?php echo htmlspecialchars($r['default_value']); ?></td>
                                            <td><?php echo htmlspecialchars($r['default_calculation_mode']); ?></td>
                                            <td><?php echo (($r['gst_supply_scope'] ?? '') === 'out_of_state') ? 'Out of state' : 'Local state'; ?></td>
                                            <td class="text-center">
                                                <a href="javascript:void(0)" onclick="editTaxMaster(<?php echo $r['id']; ?>, '<?php echo addslashes($r['name']); ?>', '<?php echo addslashes($r['default_value']); ?>', '<?php echo addslashes($r['default_calculation_mode']); ?>', <?php echo (int)$r['sort_order']; ?>, '<?php echo addslashes($r['gst_supply_scope'] ?? 'local_state'); ?>')" class="text-primary mr-2">
                                                    <i class="feather icon-edit"></i>
                                                </a>
                                                <a href="javascript:void(0)" onclick="deleteTaxMaster(<?php echo $r['id']; ?>)" class="text-danger">
                                                    <i class="feather icon-trash-2"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                } else {
                                    echo '<tr id="noTaxMasterRow"><td colspan="5" class="text-center text-muted">No Tax Found. Run sql/create_tbl_tax_master.sql then add taxes.</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="master-footer">
                    <button class="master-add-btn" onclick="$('#taxMasterId').val(''); $('#taxMasterForm')[0].reset(); $('#taxMasterGstSupplyScope').val('local_state'); $('#taxMasterModal .modal-title').text('Add Tax');" data-toggle="modal" data-target="#taxMasterModal">+ Add Tax</button>
                </div>
            </div>

            <?php
            $masters_carat_has_metal = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_carat', 'metal_id');
            $masters_carat_has_dash_img = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_carat', 'dashboard_image_path');
            $masters_carat_has_split_purity = function_exists('auragold_carat_has_split_purity') && auragold_carat_has_split_purity($conn);
            $masters_carat_metal_list = [];
            if ($masters_carat_has_metal) {
                $masters_carat_metal_list = getList('SELECT id, display_name FROM tbl_metal WHERE status = 1 ORDER BY id ASC');
                if (!is_array($masters_carat_metal_list)) {
                    $masters_carat_metal_list = [];
                }
            }
            $masters_carat_colspan = ($masters_carat_has_metal ? 5 : 4)
                + ($masters_carat_has_split_purity ? 2 : 0)
                + ($masters_carat_has_dash_img ? 1 : 0);
            ?>
            <!-- CARAT -->
            <div class="master-card">
                <div class="master-header">Carat</div>
                <div class="master-body">
                    <div class="master-scroll">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th>Name<?php echo masters_req(); ?></th>
                                <?php if ($masters_carat_has_metal) { ?><th>Metal<?php echo masters_req(); ?></th><?php } ?>
                                <?php if ($masters_carat_has_split_purity) { ?>
                                <th>Sale %<?php echo masters_req(); ?></th>
                                <th>Purchase %<?php echo masters_req(); ?></th>
                                <th>Common %</th>
                                <?php } else { ?>
                                <th>Purity %</th>
                                <?php } ?>
                                <th>Description</th>
                                <?php if ($masters_carat_has_dash_img) { ?><th style="width:44px;">Img</th><?php } ?>
                                <th style="width:70px;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="caratTableBody">
<?php
$carats = [];
if ($masters_carat_has_metal) {
    $sql = "SELECT c.id, c.name, c.purity, c.description, c.metal_id"
        . ($masters_carat_has_split_purity ? ', c.purity_sales, c.purity_purchase, c.purity_common' : '')
        . ($masters_carat_has_dash_img ? ', c.dashboard_image_path, c.dashboard_image_url' : '') . "
        FROM tbl_carat c
        WHERE c.status = 1 "
        . auragold_master_list_sql_suffix($conn, 'tbl_carat') . "
        ORDER BY c.metal_id IS NULL, c.metal_id ASC, c.id DESC";
    $tmp = getList($sql);
    if (is_array($tmp)) {
        foreach ($tmp as $crow) {
            $mn = '';
            $mid = isset($crow['metal_id']) ? (int) $crow['metal_id'] : 0;
            if ($mid > 0 && function_exists('getRecord')) {
                $mr = @getRecord('SELECT display_name FROM tbl_metal WHERE id = ' . $mid . ' LIMIT 1');
                if (is_array($mr) && isset($mr['display_name'])) {
                    $mn = trim((string) $mr['display_name']);
                }
            }
            $crow['metal_name'] = $mn;
            $carats[] = $crow;
        }
    }
} else {
    $sql = "SELECT id, name, purity, description"
        . ($masters_carat_has_split_purity ? ', purity_sales, purity_purchase, purity_common' : '')
        . ($masters_carat_has_dash_img ? ', dashboard_image_path, dashboard_image_url' : '') . "
        FROM tbl_carat 
        WHERE status = 1 " 
        . auragold_master_list_sql_suffix($conn, 'tbl_carat') . "
        ORDER BY id DESC";
    $tmp = getList($sql);
    $carats = is_array($tmp) ? $tmp : [];
}

if (is_array($carats) && count($carats) > 0) {
    foreach ($carats as $row) {
        $thumb_src = '';
        if ($masters_carat_has_dash_img) {
            $tp = trim((string) ($row['dashboard_image_path'] ?? ''));
            $tu = trim((string) ($row['dashboard_image_url'] ?? ''));
            $thumb_src = $tp !== '' ? $tp : $tu;
        }
        $edit_onclick = 'editCarat('
            . (int) ($row['id'] ?? 0) . ', '
            . json_encode((string) ($row['name'] ?? ''), JSON_UNESCAPED_UNICODE) . ', '
            . json_encode((string) ($row['description'] ?? ''), JSON_UNESCAPED_UNICODE);
        if ($masters_carat_has_metal) {
            $edit_onclick .= ', ' . (int) ($row['metal_id'] ?? 0);
        }
        if ($masters_carat_has_split_purity) {
            $edit_onclick .= ', '
                . json_encode((string) ($row['purity_sales'] ?? ''), JSON_UNESCAPED_UNICODE) . ', '
                . json_encode((string) ($row['purity_purchase'] ?? ''), JSON_UNESCAPED_UNICODE) . ', '
                . json_encode((string) ($row['purity_common'] ?? ''), JSON_UNESCAPED_UNICODE);
        } else {
            $edit_onclick .= ', '
                . json_encode((string) ($row['purity'] ?? ''), JSON_UNESCAPED_UNICODE);
        }
        $edit_onclick .= ')';
?>
    <tr id="carat_<?php echo (int) $row['id']; ?>">
    <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
    <?php if ($masters_carat_has_metal) { ?>
    <td><?php echo htmlspecialchars(($row['metal_name'] ?? '') !== '' ? (string) $row['metal_name'] : '—'); ?></td>
    <?php } ?>
    <?php if ($masters_carat_has_split_purity) { ?>
    <td><?php echo htmlspecialchars(auragold_carat_format_purity_display($row['purity_sales'] ?? '')); ?></td>
    <td><?php echo htmlspecialchars(auragold_carat_format_purity_display($row['purity_purchase'] ?? '')); ?></td>
    <td><?php echo htmlspecialchars(auragold_carat_format_purity_display($row['purity_common'] ?? '')); ?></td>
    <?php } else { ?>
    <td><?php echo isset($row['purity']) && $row['purity'] !== '' && $row['purity'] !== null ? htmlspecialchars((string) $row['purity']) : '-'; ?></td>
    <?php } ?>
    <td><?php echo htmlspecialchars((string) ($row['description'] ?? '')); ?></td>
    <?php if ($masters_carat_has_dash_img) { ?>
    <td class="text-center align-middle p-1"><?php if ($thumb_src !== '') { ?><img src="<?php echo htmlspecialchars($thumb_src, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="max-width:36px;max-height:36px;object-fit:contain;vertical-align:middle;"><?php } else { ?><span class="text-muted">—</span><?php } ?></td>
    <?php } ?>
    <td class="text-center" style="width:70px;">
        <a href="javascript:void(0)" 
           onclick="<?php echo htmlspecialchars($edit_onclick, ENT_QUOTES, 'UTF-8'); ?>"
           class="text-primary mr-2" title="Edit">
            <i class="feather icon-edit"></i>
        </a>

        <a href="javascript:void(0)" 
           onclick="deleteCarat(<?php echo (int) $row['id']; ?>)" 
           class="text-danger" title="Delete">
            <i class="feather icon-trash-2"></i>
        </a>
    </td>
</tr>

<?php
    }
} else {
?>
    <tr id="noCaratRow">
        <td colspan="<?php echo (int) $masters_carat_colspan; ?>" class="text-center text-muted">No Carat Found</td>
    </tr>
<?php } ?>
</tbody>

                    </table>
                    </div>
                </div>
                <div class="master-footer">
                    <button class="master-add-btn" data-toggle="modal" data-target="#caratModal" onclick="if (document.getElementById('caratForm')) { document.getElementById('caratForm').reset(); } resetCaratImageFields(); $('#caratModal .modal-title').text('Add Carat'); if (document.getElementById('caratMetalId')) { document.getElementById('caratMetalId').value = '1'; }">
    + Add Carat
</button>

                </div>
            </div>

            <!-- COLLECTION -->
            <div class="master-card">
                <div class="master-header">Collection</div>
                <div class="master-body">
                    <table class="table table-sm table-bordered mb-0">
    <thead>
        <tr>
            <th>Name<?php echo masters_req(); ?></th>
            <th>Description</th>
            <th style="width:90px">Action</th>
        </tr>
    </thead>
    <tbody id="collectionTableBody">

        <?php
        $rows = getList("SELECT * FROM tbl_collection WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_collection') . " ORDER BY id DESC");
        if($rows){
            foreach($rows as $r){
        ?>
        <tr id="collection_<?php echo $r['id']; ?>">
            <td><?php echo htmlspecialchars($r['name']); ?></td>
            <td><?php echo htmlspecialchars($r['description']); ?></td>
            <td class="text-center">
                <a href="javascript:void(0)"
                   onclick="editCollection(
                        <?php echo $r['id']; ?>,
                        '<?php echo addslashes($r['name']); ?>',
                        '<?php echo addslashes($r['description']); ?>'
                   )"
                   class="text-primary mr-2">
                    <i class="feather icon-edit"></i>
                </a>

                <a href="javascript:void(0)"
                   onclick="deleteCollection(<?php echo $r['id']; ?>)"
                   class="text-danger">
                    <i class="feather icon-trash-2"></i>
                </a>
            </td>
        </tr>
        <?php } } else { ?>
            <tr id="noCollectionRow">
                <td colspan="3" class="text-center text-muted">
                    No Collection Found
                </td>
            </tr>
        <?php } ?>

    </tbody>
</table>
                </div>
                <div class="master-footer">
                  <button class="master-add-btn" onclick="openCollectionModal()">+ Add Collection</button>

                </div>
            </div>

            <!-- UNIT -->
           <div class="master-card">
    <div class="master-header">Unit</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th>Formal Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="unitTableBody">
                <?php
                $units = getList("SELECT * FROM tbl_unit WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_unit') . " ORDER BY id DESC");
                if($units){
                    foreach($units as $u){
                ?>
                    <tr id="unit_<?php echo $u['id']; ?>">
                        <td><?php echo htmlspecialchars($u['name']); ?></td>
                        <td><?php echo htmlspecialchars($u['formal_name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editUnit(
                                   <?php echo $u['id']; ?>,
                                   '<?php echo addslashes($u['name']); ?>',
                                   '<?php echo addslashes($u['formal_name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteUnit(<?php echo $u['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noUnitRow">
                        <td colspan="3" class="text-center text-muted">
                            No Unit Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openUnitModal()">+ Add Unit</button>
    </div>
</div>


            <!-- REMARK -->
            <div class="master-card">
    <div class="master-header">Remark</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="remarkTableBody">
                <?php
                $remarks = getList("SELECT * FROM tbl_remark WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_remark') . " ORDER BY id DESC");
                if($remarks){
                    foreach($remarks as $r){
                ?>
                    <tr id="remark_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editRemark(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteRemark(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noRemarkRow">
                        <td colspan="2" class="text-center text-muted">
                            No Remark Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openRemarkModal()">+ Add Remark</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Unit Conversion</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th>Unit<?php echo masters_req(); ?></th>
                        <th>Conversion Rate<?php echo masters_req(); ?></th>
                        <th>Quantity<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="unitConvTableBody">
                <?php
                $sql = "
                    SELECT uc.*, u.name AS unit_name
                    FROM tbl_unit_conversion uc
                    JOIN tbl_unit u ON u.id = uc.unit_id
                    WHERE uc.status=1
                    " . auragold_master_list_sql_suffix($conn, 'tbl_unit_conversion', 'uc.branch_id') . "
                    ORDER BY uc.id DESC
                ";
                $rows = getList($sql);

                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="unitConv_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo htmlspecialchars($r['unit_name']); ?></td>
                        <td><?php echo $r['conversion_rate']; ?></td>
                        <td><?php echo $r['quantity']; ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editUnitConversion(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>',
                                   '<?php echo $r['unit_id']; ?>',
                                   '<?php echo $r['conversion_rate']; ?>',
                                   '<?php echo $r['quantity']; ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteUnitConversion(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noUnitConvRow">
                        <td colspan="5" class="text-center text-muted">
                            No Unit Conversion Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openUnitConvModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Clarity</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>
                <tbody id="clarityTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_clarity WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_clarity') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="clarity_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a onclick="editClarity(<?php echo $r['id']; ?>,'<?php echo addslashes($r['name']); ?>')" class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>
                            <a onclick="deleteClarity(<?php echo $r['id']; ?>')" class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noClarityRow">
                        <td colspan="2" class="text-center text-muted">No Clarity Found</td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openClarityModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Metal</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Display Name<?php echo masters_req(); ?></th>
                        <th>HSN Code</th>
                        <th>System Name</th>
                        <?php if ($masters_metal_has_opening_fields) { ?>
                        <th>Product Opening Name</th>
                        <th style="width:56px;">Order</th>
                        <?php } ?>
                        <?php if ($masters_metal_show_dash) { ?><th style="width:56px;">Dash</th><?php } ?>
                        <?php if ($masters_metal_has_dash_img) { ?><th style="width:44px;">Img</th><?php } ?>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="metalTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_metal WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_metal') . $masters_metal_list_order);
                if($rows){
                    foreach($rows as $r){
                        $m_thumb_src = '';
                        if ($masters_metal_has_dash_img) {
                            $mtp = trim((string) ($r['dashboard_image_path'] ?? ''));
                            $mtu = trim((string) ($r['dashboard_image_url'] ?? ''));
                            $m_thumb_src = $mtp !== '' ? $mtp : $mtu;
                        }
                ?>
                    <tr id="metal_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['display_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['hsn_code']); ?></td>
                        <td><?php echo htmlspecialchars($r['system_name']); ?></td>
                        <?php if ($masters_metal_has_opening_fields) { ?>
                        <td><?php echo htmlspecialchars((string) ($r['product_opening_name'] ?? '')); ?></td>
                        <td class="text-center"><?php echo (int) ($r['order_no'] ?? 0); ?></td>
                        <?php } ?>
                        <?php if ($masters_metal_show_dash) {
                            $m_show_dash = !empty($r['show_on_dashboard']);
                            ?>
                        <td class="text-center"><?php echo $m_show_dash ? '<span class="text-success" title="Shown on dashboard">Yes</span>' : '<span class="text-muted">—</span>'; ?></td>
                        <?php } ?>
                        <?php if ($masters_metal_has_dash_img) { ?>
                        <td class="text-center align-middle p-1"><?php if ($m_thumb_src !== '') { ?><img src="<?php echo htmlspecialchars($m_thumb_src, ENT_QUOTES, 'UTF-8'); ?>" alt="" style="max-width:36px;max-height:36px;object-fit:contain;"><?php } else { ?><span class="text-muted">—</span><?php } ?></td>
                        <?php } ?>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editMetal(
                                   <?php echo (int) $r['id']; ?>,
                                   '<?php echo addslashes((string) $r['display_name']); ?>',
                                   '<?php echo addslashes((string) $r['hsn_code']); ?>',
                                   '<?php echo addslashes((string) $r['system_name']); ?>',
                                   '<?php echo addslashes((string) ($r['product_opening_name'] ?? '')); ?>',
                                   <?php echo (int) ($r['order_no'] ?? 0); ?>
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteMetal(<?php echo (int) $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noMetalRow">
                        <td colspan="<?php echo (int) $masters_metal_table_cols; ?>" class="text-center text-muted">
                            No Metal Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openMetalModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Cut</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="cutTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_cut WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_cut') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="cut_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCut(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCut(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noCutRow">
                        <td colspan="2" class="text-center text-muted">
                            No Cut Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openCutModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Color</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="colorTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_color WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_color') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="color_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editColor(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteColor(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noColorRow">
                        <td colspan="2" class="text-center text-muted">
                            No Color Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openColorModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Shape</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="shapeTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_shape WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_shape') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="shape_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editShape(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteShape(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noShapeRow">
                        <td colspan="2" class="text-center text-muted">
                            No Shape Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openShapeModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Sieve Size</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="sieveTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_sieve_size WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_sieve_size') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="sieve_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editSieve(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteSieve(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noSieveRow">
                        <td colspan="2" class="text-center text-muted">
                            No Sieve Size Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openSieveModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Currency</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th>No Of Decimal<?php echo masters_req(); ?></th>
                        <th>Symbol</th>
                        <th>Description</th>
                        <th>Base Currency</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="currencyTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_currency WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_currency') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="currency_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo $r['decimal_places']; ?></td>
                        <td><?php echo htmlspecialchars($r['symbol']); ?></td>
                        <td><?php echo htmlspecialchars($r['description']); ?></td>
                        <td class="text-center">
                            <input type="radio" disabled <?php if($r['is_base']) echo "checked"; ?>>
                        </td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCurrency(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>',
                                   '<?php echo $r['decimal_places']; ?>',
                                   '<?php echo addslashes($r['symbol']); ?>',
                                   '<?php echo addslashes($r['description']); ?>',
                                   '<?php echo $r['is_base']; ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>
                            <a href="javascript:void(0)"
                               onclick="deleteCurrency(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noCurrencyRow">
                        <td colspan="6" class="text-center text-muted">
                            No Currency Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openCurrencyModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Currency Exchange Rate</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Currency Name<?php echo masters_req(); ?></th>
                        <th>Rate<?php echo masters_req(); ?></th>
                        <th>Description</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="currencyRateTableBody">
                <?php
                $sql = "
                    SELECT r.*, c.name AS currency_name
                    FROM tbl_currency_exchange_rate r
                    JOIN tbl_currency c ON c.id = r.currency_id
                    WHERE r.status=1
                    " . auragold_master_list_sql_suffix($conn, 'tbl_currency_exchange_rate', 'r.branch_id') . "
                    ORDER BY r.id DESC
                ";
                $rows = getList($sql);

                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="rate_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['currency_name']); ?></td>
                        <td><?php echo $r['rate']; ?></td>
                        <td><?php echo htmlspecialchars($r['description']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCurrencyRate(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo $r['currency_id']; ?>',
                                   '<?php echo $r['rate']; ?>',
                                   '<?php echo addslashes($r['description']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCurrencyRate(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noCurrencyRateRow">
                        <td colspan="4" class="text-center text-muted">
                            No Exchange Rate Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openCurrencyRateModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Size</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th>Description</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="sizeTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_size WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_size') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="size_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo htmlspecialchars($r['description']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editSize(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>',
                                   '<?php echo addslashes($r['description']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteSize(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noSizeRow">
                        <td colspan="3" class="text-center text-muted">
                            No Size Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openSizeModal()">+ Add Size</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Counter</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th>Location</th>
                        <th>Description</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="counterTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_counter WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_counter') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="counter_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo htmlspecialchars($r['location']); ?></td>
                        <td><?php echo htmlspecialchars($r['description']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCounter(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>',
                                   '<?php echo addslashes($r['location']); ?>',
                                   '<?php echo addslashes($r['description']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCounter(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noCounterRow">
                        <td colspan="4" class="text-center text-muted">
                            No Counter Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openCounterModal()">+ Add Counter</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Packet Type</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th>Weight</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="packetTypeTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_packet_type WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_packet_type') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="packet_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo $r['weight']; ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editPacketType(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>',
                                   '<?php echo $r['weight']; ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deletePacketType(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noPacketTypeRow">
                        <td colspan="3" class="text-center text-muted">
                            No Packet Type Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openPacketTypeModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Task Type</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th>Used In<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="taskTypeTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_task_type WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_task_type') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="task_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo $r['used_in']; ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editTaskType(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>',
                                   '<?php echo $r['used_in']; ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteTaskType(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noTaskTypeRow">
                        <td colspan="3" class="text-center text-muted">
                            No Task Type Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openTaskTypeModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Loan Product Type</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="loanProductTypeTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_loan_product_type WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_loan_product_type') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="loanProduct_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editLoanProductType(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteLoanProductType(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noLoanProductRow">
                        <td colspan="2" class="text-center text-muted">
                            No Loan Product Type Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openLoanProductTypeModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Loan Reason</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="loanReasonTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_loan_reason WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_loan_reason') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="loanReason_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editLoanReason(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteLoanReason(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noLoanReasonRow">
                        <td colspan="2" class="text-center text-muted">
                            No Loan Reason Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openLoanReasonModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Document Type</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="documentTypeTableBody">
                <?php
                if (!function_exists('auragold_ensure_tbl_document_types')) {
                    require_once __DIR__ . '/includes/document_types_schema.php';
                }
                auragold_ensure_tbl_document_types($conn);
                $docTypeRows = getList(
                    'SELECT * FROM tbl_document_types WHERE status=1 '
                    . auragold_master_list_sql_suffix($conn, 'tbl_document_types')
                    . ' ORDER BY id DESC'
                );
                if ($docTypeRows) {
                    foreach ($docTypeRows as $r) {
                ?>
                    <tr id="documentType_<?php echo (int) $r['id']; ?>">
                        <td><?php echo htmlspecialchars((string) $r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editDocumentType(
                                   <?php echo (int) $r['id']; ?>,
                                   '<?php echo addslashes((string) $r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteDocumentType(<?php echo (int) $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php
                    }
                } else {
                ?>
                    <tr id="noDocumentTypeRow">
                        <td colspan="2" class="text-center text-muted">
                            No Document Type Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openDocumentTypeModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Break Type</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="breakTypeTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_break_type WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_break_type') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="breakType_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editBreakType(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteBreakType(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noBreakTypeRow">
                        <td colspan="2" class="text-center text-muted">
                            No Break Type Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openBreakTypeModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Campaign Group</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="campaignGroupTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_campaign_group WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_campaign_group') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="campaignGroup_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCampaignGroup(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['name']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCampaignGroup(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noCampaignGroupRow">
                        <td colspan="2" class="text-center text-muted">
                            No Campaign Group Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openCampaignGroupModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Article</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Article Code<?php echo masters_req(); ?></th>
                        <th>Article Name<?php echo masters_req(); ?></th>
                        <th>Description</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="articleTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_article WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_article') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="article_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['article_code']); ?></td>
                        <td><?php echo htmlspecialchars($r['name']); ?></td>
                        <td><?php echo htmlspecialchars($r['description']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editArticle(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['article_code']); ?>',
                                   '<?php echo addslashes($r['name']); ?>',
                                   '<?php echo addslashes($r['description']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteArticle(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noArticleRow">
                        <td colspan="4" class="text-center text-muted">
                            No Article Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openArticleModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Cash Denominations</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Type<?php echo masters_req(); ?></th>
                        <th>Amount In Trans.<?php echo masters_req(); ?></th>
                        <th>Currency<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="cashDenominationTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_cash_denomination WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_cash_denomination') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="cashDenomination_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['type']); ?></td>
                        <td><?php echo htmlspecialchars($r['amount']); ?></td>
                        <td><?php echo htmlspecialchars($r['currency']); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCashDenomination(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['type']); ?>',
                                   '<?php echo $r['amount']; ?>',
                                   '<?php echo addslashes($r['currency']); ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCashDenomination(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noCashDenominationRow">
                        <td colspan="4" class="text-center text-muted">
                            No Cash Denominations Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openCashDenominationModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Customer Advance Policy</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Policy Name<?php echo masters_req(); ?></th>
                        <th>Days Duration<?php echo masters_req(); ?></th>
                        <th>Min % of Gold Amount<?php echo masters_req(); ?></th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="advancePolicyTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_customer_advance_policy WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_customer_advance_policy') . " ORDER BY id DESC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="advancePolicy_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['policy_name']); ?></td>
                        <td><?php echo htmlspecialchars($r['days_duration']); ?></td>
                        <td><?php echo htmlspecialchars($r['min_gold_percent']); ?>%</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editAdvancePolicy(
                                   <?php echo $r['id']; ?>,
                                   '<?php echo addslashes($r['policy_name']); ?>',
                                   '<?php echo $r['days_duration']; ?>',
                                   '<?php echo $r['min_gold_percent']; ?>'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteAdvancePolicy(<?php echo $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noAdvancePolicyRow">
                        <td colspan="4" class="text-center text-muted">
                            No Customer Advance Policy Found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openAdvancePolicyModal()">+ Add</button>
    </div>
</div>

<div class="master-card">
    <div class="master-header">Customer Type</div>

    <div class="master-body">
        <div class="master-scroll">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th>Name<?php echo masters_req(); ?></th>
                        <th>Code<?php echo masters_req(); ?></th>
                        <th>Sort</th>
                        <th style="width:90px;">Action</th>
                    </tr>
                </thead>

                <tbody id="customerTypeTableBody">
                <?php
                $rows = getList("SELECT * FROM tbl_customer_types WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_customer_types') . " ORDER BY sort_order ASC, name ASC");
                if($rows){
                    foreach($rows as $r){
                ?>
                    <tr id="customerType_<?php echo $r['id']; ?>">
                        <td><?php echo htmlspecialchars($r['name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['code'] ?? ''); ?></td>
                        <td><?php echo (int)($r['sort_order'] ?? 0); ?></td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCustomerType(
                                   <?php echo (int) $r['id']; ?>,
                                   '<?php echo addslashes($r['name'] ?? ''); ?>',
                                   '<?php echo addslashes($r['code'] ?? ''); ?>',
                                   <?php echo (int)($r['sort_order'] ?? 0); ?>
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCustomerType(<?php echo (int) $r['id']; ?>)"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                <?php } } else { ?>
                    <tr id="noCustomerTypeRow">
                        <td colspan="4" class="text-center text-muted">
                            No customer type found
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="master-footer">
        <button class="master-add-btn" onclick="openCustomerTypeModal()">+ Add</button>
    </div>
</div>


        </div>

    </div>
</div>


                         
                           
                        </div>

                        <!-- Summary Panel -->
                        
                    </div>
                </div>
                        </div><!-- end set-software-main -->
                    </div><!-- end set-software-wrapper -->
                <!-- [ content ] End -->
                
                <div class="modal fade" id="metalModal">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Metal</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="metalForm">
            <input type="hidden" id="metalId">

            <div class="form-group">
                <label>Display Name <?php echo masters_req(); ?></label>
                <input type="text" id="metalDisplayName" class="form-control">
            </div>

            <div class="form-group">
                <label>HSN Code</label>
                <input type="text" id="metalHSN" class="form-control">
            </div>

            <div class="form-group">
                <label>System Name</label>
                <input type="text" id="metalSystemName" class="form-control">
            </div>

            <?php if ($masters_metal_has_opening_fields) { ?>
            <div class="form-group">
                <label>Product Opening Name</label>
                <input type="text" id="metalProductOpeningName" class="form-control" placeholder="Shown on Product Opening metal tabs (optional)">
            </div>

            <div class="form-group">
                <label>Order No</label>
                <input type="number" id="metalOrderNo" class="form-control" min="0" step="1" value="0" placeholder="0">
            </div>
            <?php } ?>

            <?php if ($masters_metal_show_dash) { ?>
            <div class="form-group mb-2">
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="metalShowOnDashboard" value="1">
                  <label class="custom-control-label" for="metalShowOnDashboard">Show on dashboard</label>
                </div>
            </div>
            <?php } ?>
            <?php if ($masters_metal_has_dash_img) { ?>
            <div class="form-group mb-2">
                <label>Dashboard image (optional)</label>
                <input type="file" class="form-control-file form-control-sm" id="metalImageFile" accept="image/jpeg,image/png,image/gif,image/webp">
                <input type="url" class="form-control form-control-sm mt-2" id="metalImageUrl" placeholder="https://example.com/image.jpg" autocomplete="off">
                <div class="custom-control custom-checkbox mt-2">
                  <input type="checkbox" class="custom-control-input" id="metalImageClear" value="1">
                  <label class="custom-control-label" for="metalImageClear">Remove saved image &amp; URL</label>
                </div>
                <div class="mt-2">
                  <img id="metalImagePreview" src="" alt="" style="display:none;max-width:120px;max-height:120px;object-fit:contain;border:1px solid #e2e8f0;border-radius:6px;padding:4px;">
                </div>
            </div>
            <?php } ?>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveMetal()">Save</button>
      </div>

    </div>
  </div>
</div>


                <div class="modal fade" id="clarityModal">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Add Clarity</h6>
      </div>
      <div class="modal-body">
        <form id="clarityForm">
          <input type="hidden" id="clarityId">
          <div class="form-group">
            <label>Name <?php echo masters_req(); ?></label>
            <input type="text" id="clarityName" class="form-control">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary btn-sm" onclick="saveClarity()">Save</button>
      </div>
    </div>
  </div>
</div>


         <div class="modal fade" id="locationModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Location</h6>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="locationForm">
            <input type="hidden" id="locationId">

            <div class="form-group">
                <label>Location Name <?php echo masters_req(); ?></label>
                <input type="text" id="locationName" class="form-control form-control-sm">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-purple btn-sm" onclick="saveLocation()">Save</button>
      </div>

    </div>
  </div>
</div>

<!-- Tax Master Modal -->
<div class="modal fade" id="taxMasterModal" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">Add Tax</h6>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <form id="taxMasterForm">
          <input type="hidden" id="taxMasterId">
          <div class="form-group">
            <label>Tax Name <?php echo masters_req(); ?></label>
            <input type="text" id="taxMasterName" class="form-control form-control-sm" placeholder="e.g. VAT, TAX BAH">
          </div>
          <div class="form-group">
            <label>Default Value (%)</label>
            <input type="number" id="taxMasterDefaultValue" class="form-control form-control-sm" value="0" step="0.01" min="0">
          </div>
          <div class="form-group">
            <label>Calculation Mode</label>
            <select id="taxMasterCalculationMode" class="form-control form-control-sm">
              <?php
              if (!empty($calculation_modes_master)) {
                  foreach ($calculation_modes_master as $m) {
                      echo '<option value="'.htmlspecialchars($m['name']).'">'.htmlspecialchars($m['name']).'</option>';
                  }
              } else {
                  echo '<option value="Product Amount">Product Amount</option>';
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label>GST applies on supply</label>
            <select id="taxMasterGstSupplyScope" class="form-control form-control-sm">
              <option value="local_state">Local state (intra-state / CGST+SGST)</option>
              <option value="out_of_state">Out of state (inter-state / IGST)</option>
            </select>
          </div>
          <div class="form-group">
            <label>Sort Order</label>
            <input type="number" id="taxMasterSortOrder" class="form-control form-control-sm" value="0" min="0">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-purple btn-sm" onclick="saveTaxMaster()">Save</button>
      </div>
    </div>
  </div>
</div>
       
<!-- Add Carat Modal -->
<div class="modal fade" id="caratModal" tabindex="-1">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Carat</h6>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="caratForm">
<input type="hidden" id="caratId">
          <div class="form-group">
            <label>Name<?php echo masters_req(); ?></label>
            <input type="text" class="form-control form-control-sm" id="caratName" required>
          </div>
          <?php if ($masters_carat_has_metal) { ?>
          <div class="form-group">
            <label>Metal<?php echo masters_req(); ?></label>
            <select class="form-control form-control-sm" id="caratMetalId" required>
              <?php foreach ($masters_carat_metal_list as $mm) {
                  $mid = (int) ($mm['id'] ?? 0);
                  $mdn = htmlspecialchars((string) ($mm['display_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                  echo '<option value="' . $mid . '">' . $mdn . '</option>';
              } ?>
            </select>
          </div>
          <?php } ?>

          <?php if ($masters_carat_has_split_purity) { ?>
          <div class="form-group">
            <label>Sale Purity %<?php echo masters_req(); ?></label>
            <input type="number" step="0.001" class="form-control form-control-sm" id="caratPuritySales" placeholder="e.g. 91.6" required>
          </div>
          <div class="form-group">
            <label>Purchase Purity %<?php echo masters_req(); ?></label>
            <input type="number" step="0.001" class="form-control form-control-sm" id="caratPurityPurchase" placeholder="e.g. 91.6" required>
          </div>
          <div class="form-group">
            <label>Common Purity %</label>
            <input type="number" step="0.001" class="form-control form-control-sm" id="caratPurityCommon" placeholder="e.g. 91.6">
            <small class="text-muted">Used on stock/opening when Sale or Purchase purity is empty.</small>
          </div>
          <?php } else { ?>
          <div class="form-group">
            <label>Purity %</label>
            <input type="number" step="0.001" class="form-control form-control-sm" id="caratPurity">
          </div>
          <?php } ?>

          <div class="form-group">
            <label>Description</label>
            <textarea class="form-control form-control-sm" id="caratDesc"></textarea>
          </div>
          <?php if ($masters_carat_has_dash_img) { ?>
          <div class="form-group mb-2">
            <label>Dashboard image (optional)</label>
            <div class="small text-muted mb-1">Used on the rates dashboard for this metal. Upload a file and/or paste an image URL (https).</div>
            <input type="file" class="form-control-file form-control-sm" id="caratImageFile" accept="image/jpeg,image/png,image/gif,image/webp">
            <input type="url" class="form-control form-control-sm mt-2" id="caratImageUrl" placeholder="https://example.com/image.jpg" autocomplete="off">
            <div class="custom-control custom-checkbox mt-2">
              <input type="checkbox" class="custom-control-input" id="caratImageClear" value="1">
              <label class="custom-control-label" for="caratImageClear">Remove saved image &amp; URL</label>
            </div>
            <div class="mt-2">
              <img id="caratImagePreview" src="" alt="" style="display:none;max-width:120px;max-height:120px;object-fit:contain;border:1px solid #e2e8f0;border-radius:6px;padding:4px;">
            </div>
          </div>
          <?php } ?>

        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-purple btn-sm" onclick="saveCarat()">Save</button>
      </div>

    </div>
  </div>
</div>


<div class="modal fade" id="collectionModal">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Collection</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="collectionForm">
            <input type="hidden" id="collectionId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="collectionName" class="form-control form-control-sm">
            </div>

            <div class="form-group">
                <label>Description</label>
                <input type="text" id="collectionDesc" class="form-control form-control-sm">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveCollection()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="unitModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Unit</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="unitForm">
            <input type="hidden" id="unitId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="unitName" class="form-control form-control-sm">
            </div>

            <div class="form-group">
                <label>Formal Name <?php echo masters_req(); ?></label>
                <input type="text" id="unitFormal" class="form-control form-control-sm">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveUnit()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="remarkModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Remark</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="remarkForm">
            <input type="hidden" id="remarkId">

            <div class="form-group">
                <label>Remark Name <?php echo masters_req(); ?></label>
                <input type="text" id="remarkName" class="form-control form-control-sm">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveRemark()">Save</button>
      </div>

    </div>
  </div>
</div>


<div class="modal fade" id="unitConvModal">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Unit Conversion</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="unitConvForm">
            <input type="hidden" id="unitConvId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="unitConvName" class="form-control form-control-sm">
            </div>

            <div class="form-group">
                <label>Unit <?php echo masters_req(); ?></label>
                <select id="unitConvUnit" class="form-control form-control-sm">
                    <option value="">Select Unit</option>
                    <?php
                    $units = getList("SELECT id,name FROM tbl_unit WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_unit'));
                    foreach($units as $u){
                        echo "<option value='{$u['id']}'>{$u['name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Conversion Rate <?php echo masters_req(); ?></label>
                <input type="number" step="0.0001" id="unitConvRate" class="form-control form-control-sm">
            </div>

            <div class="form-group">
                <label>Quantity <?php echo masters_req(); ?></label>
                <input type="number" step="0.0001" id="unitConvQty" class="form-control form-control-sm" value="1">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveUnitConversion()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="cutModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Cut</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="cutForm">
            <input type="hidden" id="cutId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="cutName" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveCut()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="colorModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Color</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="colorForm">
            <input type="hidden" id="colorId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="colorName" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveColor()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="shapeModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Shape</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="shapeForm">
            <input type="hidden" id="shapeId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="shapeName" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveShape()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="sieveModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Sieve Size</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="sieveForm">
            <input type="hidden" id="sieveId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="sieveName" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveSieve()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="currencyModal">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Currency</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="currencyForm">
            <input type="hidden" id="currencyId">

            <div class="row">
                <div class="col-md-6 form-group">
                    <label>Name <?php echo masters_req(); ?></label>
                    <input type="text" id="currencyName" class="form-control">
                </div>

                <div class="col-md-6 form-group">
                    <label>No Of Decimal <?php echo masters_req(); ?></label>
                    <input type="number" id="currencyDecimal" class="form-control" value="2">
                </div>

                <div class="col-md-6 form-group">
                    <label>Symbol</label>
                    <input type="text" id="currencySymbol" class="form-control">
                </div>

                <div class="col-md-6 form-group">
                    <label>Description</label>
                    <input type="text" id="currencyDesc" class="form-control">
                </div>

                <div class="col-md-12 form-group">
                    <label>
                        <input type="checkbox" id="currencyBase"> Base Currency
                    </label>
                </div>
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveCurrency()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="currencyRateModal">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Exchange Rate</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="currencyRateForm">
            <input type="hidden" id="currencyRateId">

            <div class="form-group">
                <label>Currency <?php echo masters_req(); ?></label>
                <select id="currencyRateCurrency" class="form-control">
                    <option value="">Select Currency</option>
                    <?php
                    $curr = getList("SELECT id,name FROM tbl_currency WHERE status=1 " . auragold_master_list_sql_suffix($conn, 'tbl_currency'));
                    foreach($curr as $c){
                        echo "<option value='{$c['id']}'>{$c['name']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="form-group">
                <label>Rate <?php echo masters_req(); ?></label>
                <input type="number" step="0.000001" id="currencyRateValue" class="form-control">
            </div>

            <div class="form-group">
                <label>Description</label>
                <input type="text" id="currencyRateDesc" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveCurrencyRate()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="sizeModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Size</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="sizeForm">
            <input type="hidden" id="sizeId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="sizeName" class="form-control">
            </div>

            <div class="form-group">
                <label>Description</label>
                <input type="text" id="sizeDesc" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveSize()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="counterModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Counter</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="counterForm">
            <input type="hidden" id="counterId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="counterName" class="form-control">
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" id="counterLocation" class="form-control">
            </div>

            <div class="form-group">
                <label>Description</label>
                <input type="text" id="counterDesc" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveCounter()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="packetTypeModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Packet Type</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="packetTypeForm">
            <input type="hidden" id="packetTypeId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="packetTypeName" class="form-control">
            </div>

            <div class="form-group">
                <label>Weight</label>
                <input type="number" step="0.001" id="packetTypeWeight" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="savePacketType()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="taskTypeModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Task Type</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="taskTypeForm">
            <input type="hidden" id="taskTypeId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="taskTypeName" class="form-control">
            </div>

            <div class="form-group">
                <label>Used In <?php echo masters_req(); ?></label>
                <select id="taskTypeUsedIn" class="form-control">
                    <option value="Both">Both</option>
                    <option value="Task & Event">Task & Event</option>
                    <option value="Notification Message">Notification Message</option>
                </select>
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveTaskType()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="loanProductTypeModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Loan Product Type</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="loanProductTypeForm">
            <input type="hidden" id="loanProductTypeId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="loanProductTypeName" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveLoanProductType()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="customerTypeModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Customer Type</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="customerTypeForm">
            <input type="hidden" id="customerTypeId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="customerTypeName" class="form-control" maxlength="100">
            </div>

            <div class="form-group">
                <label>Code <?php echo masters_req(); ?></label>
                <input type="text" id="customerTypeCode" class="form-control" maxlength="64" placeholder="e.g. RETAILER">
            </div>

            <div class="form-group">
                <label>Sort order</label>
                <input type="number" id="customerTypeSort" class="form-control" value="0" min="0" step="1">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveCustomerType()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="loanReasonModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Loan Reason</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="loanReasonForm">
            <input type="hidden" id="loanReasonId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="loanReasonName" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveLoanReason()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="documentTypeModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Document Type</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="documentTypeForm">
            <input type="hidden" id="documentTypeId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="documentTypeName" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveDocumentType()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="breakTypeModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Break Type</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="breakTypeForm">
            <input type="hidden" id="breakTypeId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="breakTypeName" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveBreakType()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="campaignGroupModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Campaign Group</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="campaignGroupForm">
            <input type="hidden" id="campaignGroupId">

            <div class="form-group">
                <label>Name <?php echo masters_req(); ?></label>
                <input type="text" id="campaignGroupName" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveCampaignGroup()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="articleModal">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Article</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="articleForm">
            <input type="hidden" id="articleId">

            <div class="form-group">
                <label>Article Code <?php echo masters_req(); ?></label>
                <input type="text" id="articleCode" class="form-control">
            </div>

            <div class="form-group">
                <label>Article Name <?php echo masters_req(); ?></label>
                <input type="text" id="articleName" class="form-control">
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea id="articleDesc" class="form-control" rows="3"></textarea>
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveArticle()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="cashDenominationModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Cash Denomination</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="cashDenominationForm">
            <input type="hidden" id="cashDenominationId">

            <div class="form-group">
                <label>Type <?php echo masters_req(); ?></label>
                <select id="cashType" class="form-control">
                    <option value="">Select</option>
                    <option value="Note">Note</option>
                    <option value="Coin">Coin</option>
                </select>
            </div>

            <div class="form-group">
                <label>Amount In Trans. <?php echo masters_req(); ?></label>
                <input type="number" id="cashAmount" class="form-control">
            </div>

            <div class="form-group">
                <label>Currency <?php echo masters_req(); ?></label>
                 <select id="cashCurrency" class="form-control">
                    <option value="">Select</option>
                    <option value="AED">AED</option>
                    <option value="USD">USD</option>
                </select>
               
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveCashDenomination()">Save</button>
      </div>

    </div>
  </div>
</div>

<div class="modal fade" id="advancePolicyModal">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title">Add Customer Advance Policy</h6>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <form id="advancePolicyForm">
            <input type="hidden" id="advancePolicyId">

            <div class="form-group">
                <label>Policy Name <?php echo masters_req(); ?></label>
                <input type="text" id="policyName" class="form-control">
            </div>

            <div class="form-group">
                <label>Days Duration <?php echo masters_req(); ?></label>
                <input type="number" id="daysDuration" class="form-control">
            </div>

            <div class="form-group">
                <label>Min % of Gold Amount <?php echo masters_req(); ?></label>
                <input type="number" step="0.01" id="minGoldPercent" class="form-control">
            </div>
        </form>
      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="saveAdvancePolicy()">Save</button>
      </div>

    </div>
  </div>
</div>

            </div>
            <!-- [ Layout content ] End -->
        </div>
        <!-- [ Layout container ] End -->
    </div>
    <!-- Overlay -->
    <div class="layout-overlay layout-sidenav-toggle"></div>
</div>
<div id="ajaxLoader" style="display:none;">
    <div class="loader-backdrop"></div>
    <div class="loader-spinner"></div>
</div>
<!-- / Layout wrapper -->

<!-- Core scripts -->
<?php include 'footer-script.php';?>

<script>
function showLoader(){
    $("#ajaxLoader").fadeIn(150);
}

function hideLoader(){
    $("#ajaxLoader").fadeOut(150);
}

function openAdvancePolicyModal(){
    $("#advancePolicyForm")[0].reset();
    $("#advancePolicyId").val('');
    $("#advancePolicyModal .modal-title").text("Add Customer Advance Policy");
    $("#advancePolicyModal").modal("show");
}

function editAdvancePolicy(id, name, days, percent){
    $("#advancePolicyId").val(id);
    $("#policyName").val(name);
    $("#daysDuration").val(days);
    $("#minGoldPercent").val(percent);
    $("#advancePolicyModal .modal-title").text("Edit Customer Advance Policy");
    $("#advancePolicyModal").modal("show");
}

function saveAdvancePolicy(){

    let id      = $("#advancePolicyId").val();
    let name    = $("#policyName").val().trim();
    let days    = $("#daysDuration").val();
    let percent = $("#minGoldPercent").val();

    if(name === "" || days === "" || percent === ""){
        alert("All fields are required");
        return;
    }

    $.ajax({
        url: "ajax/customer-advance-policy.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            policy_name: name,
            days_duration: days,
            min_gold_percent: percent
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){

                $("#noAdvancePolicyRow").remove();

                let row = `
                    <tr id="advancePolicy_${res.id}">
                        <td>${name}</td>
                        <td>${days}</td>
                        <td>${percent}%</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editAdvancePolicy(${res.id},'${name}','${days}','${percent}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>
                            <a href="javascript:void(0)"
                               onclick="deleteAdvancePolicy(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id
                  ? $("#advancePolicy_"+res.id).replaceWith(row)
                  : $("#advancePolicyTableBody").prepend(row);

                $("#advancePolicyModal").modal("hide");
                $("#advancePolicyForm")[0].reset();
                $("#advancePolicyId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteAdvancePolicy(id){

    if(!confirm("Delete this customer advance policy?")) return;

    $.ajax({
        url: "ajax/customer-advance-policy.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#advancePolicy_"+id).remove();

                if($("#advancePolicyTableBody tr").length === 0){
                    $("#advancePolicyTableBody").html(`
                        <tr id="noAdvancePolicyRow">
                            <td colspan="4" class="text-center text-muted">
                                No Customer Advance Policy Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openCashDenominationModal(){
    $("#cashDenominationForm")[0].reset();
    $("#cashDenominationId").val('');
    $("#cashDenominationModal .modal-title").text("Add Cash Denomination");
    $("#cashDenominationModal").modal("show");
}

function editCashDenomination(id, type, amount, currency){
    $("#cashDenominationId").val(id);
    $("#cashType").val(type);
    $("#cashAmount").val(amount);
    $("#cashCurrency").val(currency);
    $("#cashDenominationModal .modal-title").text("Edit Cash Denomination");
    $("#cashDenominationModal").modal("show");
}

function saveCashDenomination(){

    let id       = $("#cashDenominationId").val();
    let type     = $("#cashType").val();
    let amount   = $("#cashAmount").val();
    let currency = $("#cashCurrency").val().trim();

    if(type === "" || amount === "" || currency === ""){
        alert("All fields are required");
        return;
    }

    $.ajax({
        url: "ajax/cash-denomination.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            type: type,
            amount: amount,
            currency: currency
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){

                $("#noCashDenominationRow").remove();

                let row = `
                    <tr id="cashDenomination_${res.id}">
                        <td>${type}</td>
                        <td>${amount}</td>
                        <td>${currency}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCashDenomination(${res.id},'${type}','${amount}','${currency}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>
                            <a href="javascript:void(0)"
                               onclick="deleteCashDenomination(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id
                  ? $("#cashDenomination_"+res.id).replaceWith(row)
                  : $("#cashDenominationTableBody").prepend(row);

                $("#cashDenominationModal").modal("hide");
                $("#cashDenominationForm")[0].reset();
                $("#cashDenominationId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteCashDenomination(id){

    if(!confirm("Delete this cash denomination?")) return;

    $.ajax({
        url: "ajax/cash-denomination.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#cashDenomination_"+id).remove();

                if($("#cashDenominationTableBody tr").length === 0){
                    $("#cashDenominationTableBody").html(`
                        <tr id="noCashDenominationRow">
                            <td colspan="4" class="text-center text-muted">
                                No Cash Denominations Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openArticleModal(){
    $("#articleForm")[0].reset();
    $("#articleId").val('');
    $("#articleModal .modal-title").text("Add Article");
    $("#articleModal").modal("show");
}

function editArticle(id, code, name, desc){
    $("#articleId").val(id);
    $("#articleCode").val(code);
    $("#articleName").val(name);
    $("#articleDesc").val(desc);
    $("#articleModal .modal-title").text("Edit Article");
    $("#articleModal").modal("show");
}

function saveArticle(){

    let id    = $("#articleId").val();
    let code  = $("#articleCode").val().trim();
    let name  = $("#articleName").val().trim();
    let desc  = $("#articleDesc").val().trim();

    if(code === "" || name === ""){
        alert("Article code and name required");
        return;
    }

    $.ajax({
        url: "ajax/article.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            article_code: code,
            name: name,
            description: desc
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){

                $("#noArticleRow").remove();

                let safeCode = code.replace(/'/g,"\\'");
                let safeName = name.replace(/'/g,"\\'");
                let safeDesc = desc.replace(/'/g,"\\'");

                let row = `
                    <tr id="article_${res.id}">
                        <td>${code}</td>
                        <td>${name}</td>
                        <td>${desc}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editArticle(${res.id},'${safeCode}','${safeName}','${safeDesc}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>
                            <a href="javascript:void(0)"
                               onclick="deleteArticle(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id
                  ? $("#article_"+res.id).replaceWith(row)
                  : $("#articleTableBody").prepend(row);

                $("#articleModal").modal("hide");
                $("#articleForm")[0].reset();
                $("#articleId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteArticle(id){

    if(!confirm("Delete this article?")) return;

    $.ajax({
        url: "ajax/article.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#article_"+id).remove();

                if($("#articleTableBody tr").length === 0){
                    $("#articleTableBody").html(`
                        <tr id="noArticleRow">
                            <td colspan="4" class="text-center text-muted">
                                No Article Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openCampaignGroupModal(){
    $("#campaignGroupForm")[0].reset();
    $("#campaignGroupId").val('');
    $("#campaignGroupModal .modal-title").text("Add Campaign Group");
    $("#campaignGroupModal").modal("show");
}

function editCampaignGroup(id, name){
    $("#campaignGroupId").val(id);
    $("#campaignGroupName").val(name);
    $("#campaignGroupModal .modal-title").text("Edit Campaign Group");
    $("#campaignGroupModal").modal("show");
}

function saveCampaignGroup(){

    let id   = $("#campaignGroupId").val();
    let name = $("#campaignGroupName").val().trim();

    if(name === ""){
        alert("Campaign group name required");
        return;
    }

    $.ajax({
        url: "ajax/campaign-group.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){

                $("#noCampaignGroupRow").remove();

                let safeName = name.replace(/'/g,"\\'");

                let row = `
                    <tr id="campaignGroup_${res.id}">
                        <td>${name}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCampaignGroup(${res.id},'${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCampaignGroup(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id
                  ? $("#campaignGroup_"+res.id).replaceWith(row)
                  : $("#campaignGroupTableBody").prepend(row);

                $("#campaignGroupModal").modal("hide");
                $("#campaignGroupForm")[0].reset();
                $("#campaignGroupId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteCampaignGroup(id){

    if(!confirm("Delete this campaign group?")) return;

    $.ajax({
        url: "ajax/campaign-group.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#campaignGroup_"+id).remove();

                if($("#campaignGroupTableBody tr").length === 0){
                    $("#campaignGroupTableBody").html(`
                        <tr id="noCampaignGroupRow">
                            <td colspan="2" class="text-center text-muted">
                                No Campaign Group Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openBreakTypeModal(){
    $("#breakTypeForm")[0].reset();
    $("#breakTypeId").val('');
    $("#breakTypeModal .modal-title").text("Add Break Type");
    $("#breakTypeModal").modal("show");
}

function editBreakType(id, name){
    $("#breakTypeId").val(id);
    $("#breakTypeName").val(name);
    $("#breakTypeModal .modal-title").text("Edit Break Type");
    $("#breakTypeModal").modal("show");
}

function saveBreakType(){

    let id   = $("#breakTypeId").val();
    let name = $("#breakTypeName").val().trim();

    if(name === ""){
        alert("Break type name required");
        return;
    }

    $.ajax({
        url: "ajax/break-type.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){

            if(res.status === "success"){

                $("#noBreakTypeRow").remove();

                let safeName = name.replace(/'/g,"\\'");

                let row = `
                    <tr id="breakType_${res.id}">
                        <td>${name}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editBreakType(${res.id},'${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteBreakType(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#breakType_"+res.id).replaceWith(row)
                   : $("#breakTypeTableBody").prepend(row);

                $("#breakTypeModal").modal("hide");
                $("#breakTypeForm")[0].reset();
                $("#breakTypeId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteBreakType(id){

    if(!confirm("Delete this break type?")) return;

    $.ajax({
        url: "ajax/break-type.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#breakType_"+id).remove();

                if($("#breakTypeTableBody tr").length === 0){
                    $("#breakTypeTableBody").html(`
                        <tr id="noBreakTypeRow">
                            <td colspan="2" class="text-center text-muted">
                                No Break Type Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openLoanReasonModal(){
    $("#loanReasonForm")[0].reset();
    $("#loanReasonId").val('');
    $("#loanReasonModal .modal-title").text("Add Loan Reason");
    $("#loanReasonModal").modal("show");
}

function editLoanReason(id, name){
    $("#loanReasonId").val(id);
    $("#loanReasonName").val(name);
    $("#loanReasonModal .modal-title").text("Edit Loan Reason");
    $("#loanReasonModal").modal("show");
}

function saveLoanReason(){

    let id   = $("#loanReasonId").val();
    let name = $("#loanReasonName").val().trim();

    if(name === ""){
        alert("Loan reason name required");
        return;
    }

    $.ajax({
        url: "ajax/loan-reason.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){

            if(res.status === "success"){

                $("#noLoanReasonRow").remove();

                let safeName = name.replace(/'/g,"\\'");

                let row = `
                    <tr id="loanReason_${res.id}">
                        <td>${name}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editLoanReason(${res.id},'${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteLoanReason(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#loanReason_"+res.id).replaceWith(row)
                   : $("#loanReasonTableBody").prepend(row);

                $("#loanReasonModal").modal("hide");
                $("#loanReasonForm")[0].reset();
                $("#loanReasonId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteLoanReason(id){

    if(!confirm("Delete this loan reason?")) return;

    $.ajax({
        url: "ajax/loan-reason.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#loanReason_"+id).remove();

                if($("#loanReasonTableBody tr").length === 0){
                    $("#loanReasonTableBody").html(`
                        <tr id="noLoanReasonRow">
                            <td colspan="2" class="text-center text-muted">
                                No Loan Reason Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openDocumentTypeModal(){
    $("#documentTypeForm")[0].reset();
    $("#documentTypeId").val('');
    $("#documentTypeModal .modal-title").text("Add Document Type");
    $("#documentTypeModal").modal("show");
}

function editDocumentType(id, name){
    $("#documentTypeId").val(id);
    $("#documentTypeName").val(name);
    $("#documentTypeModal .modal-title").text("Edit Document Type");
    $("#documentTypeModal").modal("show");
}

function saveDocumentType(){

    let id   = $("#documentTypeId").val();
    let name = $("#documentTypeName").val().trim();

    if(name === ""){
        alert("Document type name is required");
        return;
    }

    $.ajax({
        url: "ajax/document-type.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){

            if(res.status === "success"){

                $("#noDocumentTypeRow").remove();

                let safeName = name.replace(/'/g,"\\'");

                let row = `
                    <tr id="documentType_${res.id}">
                        <td>${name}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editDocumentType(${res.id},'${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteDocumentType(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#documentType_"+res.id).replaceWith(row)
                   : $("#documentTypeTableBody").prepend(row);

                $("#documentTypeModal").modal("hide");
                $("#documentTypeForm")[0].reset();
                $("#documentTypeId").val('');
            } else {
                alert(res.message || "Save failed");
            }
        },

        complete: hideLoader
    });
}

function deleteDocumentType(id){

    if(!confirm("Delete this document type?")) return;

    $.ajax({
        url: "ajax/document-type.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#documentType_"+id).remove();

                if($("#documentTypeTableBody tr").length === 0){
                    $("#documentTypeTableBody").html(`
                        <tr id="noDocumentTypeRow">
                            <td colspan="2" class="text-center text-muted">
                                No Document Type Found
                            </td>
                        </tr>
                    `);
                }
            } else {
                alert(res.message || "Delete failed");
            }
        },

        complete: hideLoader
    });
}


function openLoanProductTypeModal(){
    $("#loanProductTypeForm")[0].reset();
    $("#loanProductTypeId").val('');
    $("#loanProductTypeModal .modal-title").text("Add Loan Product Type");
    $("#loanProductTypeModal").modal("show");
}

function editLoanProductType(id, name){
    $("#loanProductTypeId").val(id);
    $("#loanProductTypeName").val(name);
    $("#loanProductTypeModal .modal-title").text("Edit Loan Product Type");
    $("#loanProductTypeModal").modal("show");
}

function saveLoanProductType(){

    let id   = $("#loanProductTypeId").val();
    let name = $("#loanProductTypeName").val().trim();

    if(name === ""){
        alert("Loan product type name required");
        return;
    }

    $.ajax({
        url: "ajax/loan-product-type.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){

            if(res.status === "success"){

                $("#noLoanProductRow").remove();

                let safeName = name.replace(/'/g,"\\'");

                let row = `
                    <tr id="loanProduct_${res.id}">
                        <td>${name}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editLoanProductType(${res.id},'${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteLoanProductType(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#loanProduct_"+res.id).replaceWith(row)
                   : $("#loanProductTypeTableBody").prepend(row);

                $("#loanProductTypeModal").modal("hide");
                $("#loanProductTypeForm")[0].reset();
                $("#loanProductTypeId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteLoanProductType(id){

    if(!confirm("Delete this loan product type?")) return;

    $.ajax({
        url: "ajax/loan-product-type.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#loanProduct_"+id).remove();

                if($("#loanProductTypeTableBody tr").length === 0){
                    $("#loanProductTypeTableBody").html(`
                        <tr id="noLoanProductRow">
                            <td colspan="2" class="text-center text-muted">
                                No Loan Product Type Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}

function openCustomerTypeModal(){
    $("#customerTypeForm")[0].reset();
    $("#customerTypeId").val("");
    $("#customerTypeSort").val(0);
    $("#customerTypeModal .modal-title").text("Add Customer Type");
    $("#customerTypeModal").modal("show");
}

function editCustomerType(id, name, code, sort){
    $("#customerTypeId").val(id);
    $("#customerTypeName").val(name);
    $("#customerTypeCode").val(code);
    $("#customerTypeSort").val(sort);
    $("#customerTypeModal .modal-title").text("Edit Customer Type");
    $("#customerTypeModal").modal("show");
}

function saveCustomerType(){

    let id   = $("#customerTypeId").val();
    let name = $("#customerTypeName").val().trim();
    let code = $("#customerTypeCode").val().trim();
    let sort = parseInt($("#customerTypeSort").val(), 10) || 0;

    if(name === ""){
        alert("Customer type name required");
        return;
    }
    if(code === ""){
        alert("Customer type code required");
        return;
    }

    $.ajax({
        url: "ajax/customer-type.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            code: code,
            sort_order: sort
        },

        beforeSend: showLoader,

        success: function(res){

            if(res.status === "error" && res.message){
                alert(res.message);
                return;
            }
            if(res.status === "success"){

                $("#noCustomerTypeRow").remove();

                let safeName = name.replace(/'/g,"\\'");
                let safeCode = code.replace(/'/g,"\\'");

                let row = `
                    <tr id="customerType_${res.id}">
                        <td>${$("<div/>").text(name).html()}</td>
                        <td>${$("<div/>").text(code).html()}</td>
                        <td>${sort}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCustomerType(${res.id},
                               '${safeName}',
                               '${safeCode}',
                               ${sort})"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCustomerType(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#customerType_"+res.id).replaceWith(row)
                   : $("#customerTypeTableBody").append(row);

                sortCustomerTypeRows();
                $("#customerTypeModal").modal("hide");
                $("#customerTypeForm")[0].reset();
                $("#customerTypeId").val("");
            }
        },

        complete: hideLoader
    });
}

function sortCustomerTypeRows(){
    let $tb = $("#customerTypeTableBody");
    let $rows = $tb.children("tr").get();
    $rows.sort(function(a, b){
        let sa = parseInt($(a).find("td").eq(2).text(), 10) || 0;
        let sb = parseInt($(b).find("td").eq(2).text(), 10) || 0;
        if(sa !== sb) return sa - sb;
        return $(a).find("td").eq(0).text().toLowerCase().localeCompare($(b).find("td").eq(0).text().toLowerCase());
    });
    $.each($rows, function(_, r){ $tb.append(r); });
}

function deleteCustomerType(id){

    if(!confirm("Delete this customer type?")) return;

    $.ajax({
        url: "ajax/customer-type.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "error" && res.message){
                alert(res.message);
                return;
            }
            if(res.status === "success"){
                $("#customerType_"+id).remove();

                if($("#customerTypeTableBody tr").length === 0){
                    $("#customerTypeTableBody").html(`
                        <tr id="noCustomerTypeRow">
                            <td colspan="4" class="text-center text-muted">
                            No customer type found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openTaskTypeModal(){
    $("#taskTypeForm")[0].reset();
    $("#taskTypeId").val('');
    $("#taskTypeModal .modal-title").text("Add Task Type");
    $("#taskTypeModal").modal("show");
}

function editTaskType(id, name, usedIn){
    $("#taskTypeId").val(id);
    $("#taskTypeName").val(name);
    $("#taskTypeUsedIn").val(usedIn);
    $("#taskTypeModal .modal-title").text("Edit Task Type");
    $("#taskTypeModal").modal("show");
}

function saveTaskType(){

    let id     = $("#taskTypeId").val();
    let name   = $("#taskTypeName").val().trim();
    let usedIn = $("#taskTypeUsedIn").val();

    if(name === ""){
        alert("Task type name required");
        return;
    }

    $.ajax({
        url: "ajax/task-type.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            used_in: usedIn
        },

        beforeSend: showLoader,

        success: function(res){

            if(res.status === "success"){

                $("#noTaskTypeRow").remove();

                let safeName = name.replace(/'/g,"\\'");

                let row = `
                    <tr id="task_${res.id}">
                        <td>${name}</td>
                        <td>${usedIn}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editTaskType(${res.id},
                               '${safeName}',
                               '${usedIn}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteTaskType(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#task_"+res.id).replaceWith(row)
                   : $("#taskTypeTableBody").prepend(row);

                $("#taskTypeModal").modal("hide");
                $("#taskTypeForm")[0].reset();
                $("#taskTypeId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteTaskType(id){

    if(!confirm("Delete this task type?")) return;

    $.ajax({
        url: "ajax/task-type.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#task_"+id).remove();

                if($("#taskTypeTableBody tr").length === 0){
                    $("#taskTypeTableBody").html(`
                        <tr id="noTaskTypeRow">
                            <td colspan="3" class="text-center text-muted">
                                No Task Type Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openPacketTypeModal(){
    $("#packetTypeForm")[0].reset();
    $("#packetTypeId").val('');
    $("#packetTypeModal .modal-title").text("Add Packet Type");
    $("#packetTypeModal").modal("show");
}

function editPacketType(id, name, weight){
    $("#packetTypeId").val(id);
    $("#packetTypeName").val(name);
    $("#packetTypeWeight").val(weight);
    $("#packetTypeModal .modal-title").text("Edit Packet Type");
    $("#packetTypeModal").modal("show");
}

function savePacketType(){

    let id     = $("#packetTypeId").val();
    let name   = $("#packetTypeName").val().trim();
    let weight = $("#packetTypeWeight").val();

    if(name === ""){
        alert("Packet type name required");
        return;
    }

    $.ajax({
        url: "ajax/packet-type.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            weight: weight
        },

        beforeSend: showLoader,

        success: function(res){

            if(res.status === "success"){

                $("#noPacketTypeRow").remove();

                let safeName = name.replace(/'/g,"\\'");

                let row = `
                    <tr id="packet_${res.id}">
                        <td>${name}</td>
                        <td>${weight || 0}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editPacketType(${res.id},
                               '${safeName}',
                               '${weight}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deletePacketType(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#packet_"+res.id).replaceWith(row)
                   : $("#packetTypeTableBody").prepend(row);

                $("#packetTypeModal").modal("hide");
                $("#packetTypeForm")[0].reset();
                $("#packetTypeId").val('');
            }
        },

        complete: hideLoader
    });
}

function deletePacketType(id){

    if(!confirm("Delete this packet type?")) return;

    $.ajax({
        url: "ajax/packet-type.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#packet_"+id).remove();

                if($("#packetTypeTableBody tr").length === 0){
                    $("#packetTypeTableBody").html(`
                        <tr id="noPacketTypeRow">
                            <td colspan="3" class="text-center text-muted">
                                No Packet Type Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openCounterModal(){
    $("#counterForm")[0].reset();
    $("#counterId").val('');
    $("#counterModal .modal-title").text("Add Counter");
    $("#counterModal").modal("show");
}

function editCounter(id, name, location, desc){
    $("#counterId").val(id);
    $("#counterName").val(name);
    $("#counterLocation").val(location);
    $("#counterDesc").val(desc);
    $("#counterModal .modal-title").text("Edit Counter");
    $("#counterModal").modal("show");
}

function saveCounter(){

    let id       = $("#counterId").val();
    let name     = $("#counterName").val().trim();
    let location = $("#counterLocation").val().trim();
    let desc     = $("#counterDesc").val().trim();

    if(name === ""){
        alert("Counter name required");
        return;
    }

    $.ajax({
        url: "ajax/counter.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            location: location,
            description: desc
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){

                $("#noCounterRow").remove();

                let safeName = name.replace(/'/g,"\\'");
                let safeLoc  = location.replace(/'/g,"\\'");
                let safeDesc = desc.replace(/'/g,"\\'");

                let row = `
                    <tr id="counter_${res.id}">
                        <td>${name}</td>
                        <td>${location || '-'}</td>
                        <td>${desc || '-'}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCounter(${res.id},
                               '${safeName}',
                               '${safeLoc}',
                               '${safeDesc}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCounter(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#counter_"+res.id).replaceWith(row)
                   : $("#counterTableBody").prepend(row);

                $("#counterModal").modal("hide");
                $("#counterForm")[0].reset();
                $("#counterId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteCounter(id){

    if(!confirm("Delete this counter?")) return;

    $.ajax({
        url: "ajax/counter.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#counter_"+id).remove();

                if($("#counterTableBody tr").length === 0){
                    $("#counterTableBody").html(`
                        <tr id="noCounterRow">
                            <td colspan="4" class="text-center text-muted">
                                No Counter Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openSizeModal(){
    $("#sizeForm")[0].reset();
    $("#sizeId").val('');
    $("#sizeModal .modal-title").text("Add Size");
    $("#sizeModal").modal("show");
}

function editSize(id, name, desc){
    $("#sizeId").val(id);
    $("#sizeName").val(name);
    $("#sizeDesc").val(desc);
    $("#sizeModal .modal-title").text("Edit Size");
    $("#sizeModal").modal("show");
}

function saveSize(){

    let id   = $("#sizeId").val();
    let name = $("#sizeName").val().trim();
    let desc = $("#sizeDesc").val().trim();

    if(name === ""){
        alert("Size name required");
        return;
    }

    $.ajax({
        url: "ajax/size.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            description: desc
        },

        beforeSend: showLoader,

        success: function(res){

            if(res.status === "success"){

                $("#noSizeRow").remove();

                let safeName = name.replace(/'/g,"\\'");
                let safeDesc = desc.replace(/'/g,"\\'");

                let row = `
                    <tr id="size_${res.id}">
                        <td>${name}</td>
                        <td>${desc || '-'}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editSize(${res.id},
                               '${safeName}',
                               '${safeDesc}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteSize(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#size_"+res.id).replaceWith(row)
                   : $("#sizeTableBody").prepend(row);

                $("#sizeModal").modal("hide");
                $("#sizeForm")[0].reset();
                $("#sizeId").val('');
            }
        },

        complete: hideLoader
    });
}

function deleteSize(id){

    if(!confirm("Delete this size?")) return;

    $.ajax({
        url: "ajax/size.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#size_"+id).remove();

                if($("#sizeTableBody tr").length === 0){
                    $("#sizeTableBody").html(`
                        <tr id="noSizeRow">
                            <td colspan="3" class="text-center text-muted">
                                No Size Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}


function openCurrencyRateModal(){
    $("#currencyRateForm")[0].reset();
    $("#currencyRateId").val('');
    $("#currencyRateModal .modal-title").text("Add Exchange Rate");
    $("#currencyRateModal").modal("show");
}

function editCurrencyRate(id, currencyId, rate, desc){
    $("#currencyRateId").val(id);
    $("#currencyRateCurrency").val(currencyId);
    $("#currencyRateValue").val(rate);
    $("#currencyRateDesc").val(desc);
    $("#currencyRateModal .modal-title").text("Edit Exchange Rate");
    $("#currencyRateModal").modal("show");
}

function saveCurrencyRate(){

    let id       = $("#currencyRateId").val();
    let currency = $("#currencyRateCurrency").val();
    let rate     = $("#currencyRateValue").val();
    let desc     = $("#currencyRateDesc").val().trim();

    if(currency === "" || rate === ""){
        alert("Currency and rate required");
        return;
    }

    let currencyName = $("#currencyRateCurrency option:selected").text();
    let safeDesc = desc.replace(/'/g,"\\'");

    $.ajax({
        url: "ajax/currency-exchange-rate.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            currency_id: currency,
            rate: rate,
            description: desc
        },

        beforeSend: function(){
            showLoader();
        },

        success: function(res){

            if(res.status === "success"){

                $("#noCurrencyRateRow").remove();

                let row = `
                    <tr id="rate_${res.id}">
                        <td>${currencyName}</td>
                        <td>${rate}</td>
                        <td>${desc || '-'}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCurrencyRate(
                                   ${res.id},
                                   '${currency}',
                                   '${rate}',
                                   '${safeDesc}'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCurrencyRate(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#rate_" + res.id).replaceWith(row);
                }else{
                    $("#currencyRateTableBody").prepend(row);
                }

                $("#currencyRateForm")[0].reset();
                $("#currencyRateId").val('');
                $("#currencyRateModal").modal("hide");

            }else{
                alert(res.message || "Save failed");
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: function(){
            hideLoader();
        }
    });
}


function deleteCurrencyRate(id){

    if(!confirm("Delete this exchange rate?")) return;

    $.ajax({
        url: "ajax/currency-exchange-rate.php",
        type: "POST",              // ✅ FIXED
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: function(){
            showLoader();
        },

        success: function(res){
            if(res.status === "success"){
                $("#rate_"+id).remove();

                // Optional empty state
                if($("#currencyRateTableBody tr").length === 0){
                    $("#currencyRateTableBody").html(`
                        <tr id="noCurrencyRateRow">
                            <td colspan="4" class="text-center text-muted">
                                No Exchange Rate Found
                            </td>
                        </tr>
                    `);
                }
            }else{
                alert(res.message || "Delete failed");
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: function(){
            hideLoader();
        }
    });
}


function openCurrencyModal(){
    $("#currencyForm")[0].reset();
    $("#currencyId").val('');
    $("#currencyModal .modal-title").text("Add Currency");
    $("#currencyModal").modal("show");
}

function editCurrency(id,name,decimal,symbol,desc,isBase){
    $("#currencyId").val(id);
    $("#currencyName").val(name);
    $("#currencyDecimal").val(decimal);
    $("#currencySymbol").val(symbol);
    $("#currencyDesc").val(desc);
    $("#currencyBase").prop("checked", isBase==1);
    $("#currencyModal .modal-title").text("Edit Currency");
    $("#currencyModal").modal("show");
}

function saveCurrency(){

    let id      = $("#currencyId").val();
    let name    = $("#currencyName").val().trim();
    let decimal = $("#currencyDecimal").val();
    let symbol  = $("#currencySymbol").val().trim();
    let desc    = $("#currencyDesc").val().trim();
    let isBase  = $("#currencyBase").is(":checked") ? 1 : 0;

    if(name === "" || decimal === ""){
        alert("Required fields missing");
        return;
    }

    $.ajax({
        url: "ajax/currency.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            decimal_places: decimal,
            symbol: symbol,
            description: desc,
            is_base: isBase
        },

        beforeSend: function(){
            showLoader();
        },

        success: function(res){

            if(res.status === "success"){

                $("#noCurrencyRow").remove();

                /* If base currency changed, update all radios */
                if(isBase === 1){
                    $("#currencyTableBody input[type=radio]").prop("checked", false);
                }

                let row = `
                    <tr id="currency_${res.id}">
                        <td>${name}</td>
                        <td>${decimal}</td>
                        <td>${symbol || '-'}</td>
                        <td>${desc || '-'}</td>
                        <td class="text-center">
                            <input type="radio" disabled ${isBase ? 'checked' : ''}>
                        </td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCurrency(
                                   ${res.id},
                                   '${name.replace(/'/g,"\\'")}',
                                   '${decimal}',
                                   '${symbol.replace(/'/g,"\\'")}',
                                   '${desc.replace(/'/g,"\\'")}',
                                   '${isBase}'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCurrency(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#currency_" + res.id).replaceWith(row);
                }else{
                    $("#currencyTableBody").prepend(row);
                }

                $("#currencyForm")[0].reset();
                $("#currencyId").val('');
                $("#currencyModal").modal("hide");

            }else{
                alert(res.message || "Save failed");
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: function(){
            hideLoader();
        }
    });
}


function deleteCurrency(id){

    if(!confirm("Delete this currency?")) return;

    $.ajax({
        url: "ajax/currency.php",
        type: "POST",
        dataType: "json",
        data: { action:"delete", id:id },

        beforeSend: showLoader,

        success: function(res){
            if(res.status==="success"){
                $("#currency_"+id).remove();
            }
        },

        complete: hideLoader
    });
}


function openSieveModal(){
    $("#sieveForm")[0].reset();
    $("#sieveId").val('');
    $("#sieveModal .modal-title").text("Add Sieve Size");
    $("#sieveModal").modal("show");
}

function editSieve(id, name){
    $("#sieveId").val(id);
    $("#sieveName").val(name);
    $("#sieveModal .modal-title").text("Edit Sieve Size");
    $("#sieveModal").modal("show");
}

function saveSieve(){

    let id   = $("#sieveId").val();
    let name = $("#sieveName").val().trim();

    if(name === ""){
        alert("Name required");
        return;
    }

    $.ajax({
        url: "ajax/sieve-size.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){

                $("#noSieveRow").remove();

                let safeName = $('<div>').text(res.name).html();

                let row = `
                    <tr id="sieve_${res.id}">
                        <td>${safeName}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editSieve(${res.id}, '${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteSieve(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#sieve_" + res.id).replaceWith(row);
                }else{
                    $("#sieveTableBody").prepend(row);
                }

                $("#sieveForm")[0].reset();
                $("#sieveId").val('');
                $("#sieveModal").modal("hide");

            }else{
                alert(res.message || "Operation failed");
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    });
}

function deleteSieve(id){

    if(!confirm("Delete this sieve size?")) return;

    $.ajax({
        url: "ajax/sieve-size.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "delete",
            id: id
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#sieve_" + id).remove();

                if($("#sieveTableBody tr").length === 0){
                    $("#sieveTableBody").html(`
                        <tr id="noSieveRow">
                            <td colspan="2" class="text-center text-muted">
                                No Sieve Size Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    });
}


function openShapeModal(){
    $("#shapeForm")[0].reset();
    $("#shapeId").val('');
    $("#shapeModal .modal-title").text("Add Shape");
    $("#shapeModal").modal("show");
}

function editShape(id, name){
    $("#shapeId").val(id);
    $("#shapeName").val(name);
    $("#shapeModal .modal-title").text("Edit Shape");
    $("#shapeModal").modal("show");
}

function saveShape(){

    let id   = $("#shapeId").val();
    let name = $("#shapeName").val().trim();

    if(name === ""){
        alert("Name required");
        return;
    }

    $.ajax({
        url: "ajax/shape.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){

                $("#noShapeRow").remove();

                let safeName = $('<div>').text(res.name).html();

                let row = `
                    <tr id="shape_${res.id}">
                        <td>${safeName}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editShape(${res.id}, '${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteShape(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#shape_" + res.id).replaceWith(row);
                }else{
                    $("#shapeTableBody").prepend(row);
                }

                $("#shapeForm")[0].reset();
                $("#shapeId").val('');
                $("#shapeModal").modal("hide");

            }else{
                alert(res.message || "Operation failed");
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    });
}

function deleteShape(id){

    if(!confirm("Delete this shape?")) return;

    $.ajax({
        url: "ajax/shape.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "delete",
            id: id
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#shape_" + id).remove();

                if($("#shapeTableBody tr").length === 0){
                    $("#shapeTableBody").html(`
                        <tr id="noShapeRow">
                            <td colspan="2" class="text-center text-muted">
                                No Shape Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    });
}


function openColorModal(){
    $("#colorForm")[0].reset();
    $("#colorId").val('');
    $("#colorModal .modal-title").text("Add Color");
    $("#colorModal").modal("show");
}

function editColor(id, name){
    $("#colorId").val(id);
    $("#colorName").val(name);
    $("#colorModal .modal-title").text("Edit Color");
    $("#colorModal").modal("show");
}

function saveColor(){

    let id   = $("#colorId").val();
    let name = $("#colorName").val().trim();

    if(name === ""){
        alert("Name required");
        return;
    }

    $.ajax({
        url: "ajax/color.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){

                $("#noColorRow").remove();

                let safeName = $('<div>').text(res.name).html();

                let row = `
                    <tr id="color_${res.id}">
                        <td>${safeName}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editColor(${res.id}, '${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteColor(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#color_" + res.id).replaceWith(row);
                }else{
                    $("#colorTableBody").prepend(row);
                }

                $("#colorForm")[0].reset();
                $("#colorId").val('');
                $("#colorModal").modal("hide");

            }else{
                alert(res.message || "Operation failed");
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    });
}

function deleteColor(id){

    if(!confirm("Delete this color?")) return;

    $.ajax({
        url: "ajax/color.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "delete",
            id: id
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#color_" + id).remove();

                if($("#colorTableBody tr").length === 0){
                    $("#colorTableBody").html(`
                        <tr id="noColorRow">
                            <td colspan="2" class="text-center text-muted">
                                No Color Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    });
}


function openCutModal(){
    $("#cutForm")[0].reset();
    $("#cutId").val('');
    $("#cutModal .modal-title").text("Add Cut");
    $("#cutModal").modal("show");
}

function editCut(id, name){
    $("#cutId").val(id);
    $("#cutName").val(name);
    $("#cutModal .modal-title").text("Edit Cut");
    $("#cutModal").modal("show");
}

function saveCut(){

    let id   = $("#cutId").val();
    let name = $("#cutName").val().trim();

    if(name === ""){
        alert("Name required");
        return;
    }

    $.ajax({
        url: "ajax/cut.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){

            if(res.status === "success"){

                $("#noCutRow").remove();

                let safeName = $('<div>').text(res.name).html();

                let row = `
                    <tr id="cut_${res.id}">
                        <td>${safeName}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCut(${res.id}, '${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCut(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#cut_" + res.id).replaceWith(row);
                }else{
                    $("#cutTableBody").prepend(row);
                }

                $("#cutForm")[0].reset();
                $("#cutId").val('');
                $("#cutModal").modal("hide");

            }else{
                alert(res.message || "Operation failed");
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    });
}

function deleteCut(id){

    if(!confirm("Delete this cut?")) return;

    $.ajax({
        url: "ajax/cut.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "delete",
            id: id
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#cut_" + id).remove();

                if($("#cutTableBody tr").length === 0){
                    $("#cutTableBody").html(`
                        <tr id="noCutRow">
                            <td colspan="2" class="text-center text-muted">
                                No Cut Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    });
}


function resetMetalImageFields() {
    if (!$("#metalImageUrl").length) {
        return;
    }
    $("#metalImageFile").val("");
    $("#metalImageUrl").val("");
    $("#metalImageClear").prop("checked", false);
    $("#metalImagePreview").hide().attr("src", "");
}

function loadMetalImageForEdit(id) {
    var needFetch = $("#metalImageUrl").length || $("#metalShowOnDashboard").length || $("#metalProductOpeningName").length;
    if (!needFetch) {
        return;
    }
    var nid = parseInt(id, 10);
    if ($("#metalImageUrl").length) {
        $("#metalImageFile").val("");
        $("#metalImageClear").prop("checked", false);
        $("#metalImagePreview").hide().attr("src", "");
        if (!nid || nid <= 0) {
            $("#metalImageUrl").val("");
        }
    } else if ($("#metalShowOnDashboard").length && (!nid || nid <= 0)) {
        $("#metalShowOnDashboard").prop("checked", false);
        return;
    }
    if (!nid || nid <= 0) {
        return;
    }
    jQuery.getJSON("ajax/metal.php", { action: "get", id: nid })
        .done(function (res) {
            if (!res || res.status !== "success" || !res.row) {
                if ($("#metalImageUrl").length) {
                    $("#metalImageUrl").val("");
                }
                return;
            }
            var r = res.row;
            if ($("#metalImageUrl").length) {
                $("#metalImageUrl").val(r.dashboard_image_url || "");
                var src = (r.dashboard_image_path || r.dashboard_image_url || "").trim();
                if (src) {
                    $("#metalImagePreview").attr("src", src).show();
                }
            }
            if ($("#metalShowOnDashboard").length) {
                $("#metalShowOnDashboard").prop(
                    "checked",
                    !!(parseInt(String(r.show_on_dashboard || ""), 10) === 1)
                );
            }
            if ($("#metalProductOpeningName").length) {
                $("#metalProductOpeningName").val(r.product_opening_name || "");
            }
            if ($("#metalOrderNo").length) {
                $("#metalOrderNo").val(parseInt(String(r.order_no || "0"), 10) || 0);
            }
        });
}

function openMetalModal(){
    $("#metalForm")[0].reset();
    $("#metalId").val('');
    resetMetalImageFields();
    $("#metalModal .modal-title").text("Add Metal");
    $("#metalModal").modal("show");
    if ($("#metalShowOnDashboard").length) {
        $("#metalShowOnDashboard").prop("checked", false);
    }
}

function editMetal(id, name, hsn, system, productOpeningName, orderNo){
    $("#metalId").val(id);
    $("#metalDisplayName").val(name);
    $("#metalHSN").val(hsn);
    $("#metalSystemName").val(system);
    if ($("#metalProductOpeningName").length) {
        $("#metalProductOpeningName").val(productOpeningName || "");
    }
    if ($("#metalOrderNo").length) {
        $("#metalOrderNo").val(parseInt(String(orderNo || "0"), 10) || 0);
    }
    loadMetalImageForEdit(id);
    $("#metalModal .modal-title").text("Edit Metal");
    $("#metalModal").modal("show");
}

function saveMetal(){

    let id     = $("#metalId").val();
    let name   = $("#metalDisplayName").val().trim();
    let hsn    = $("#metalHSN").val().trim();
    let system = $("#metalSystemName").val().trim();
    let productOpeningName = $("#metalProductOpeningName").length ? $("#metalProductOpeningName").val().trim() : "";
    let orderNo = $("#metalOrderNo").length ? (parseInt($("#metalOrderNo").val(), 10) || 0) : 0;

    if(name === ""){
        alert("Display Name required");
        return;
    }

    let useFormData = <?php echo ($masters_metal_has_dash_img || $masters_metal_show_dash) ? 'true' : 'false'; ?>;

    let ajaxOpts = {
        url: "ajax/metal.php",
        type: "POST",
        dataType: "json",
        beforeSend: showLoader,
        success: function(res){
            if(res.status === "success"){

                $("#noMetalRow").remove();

                let safeName = $('<div>').text(res.display_name).html();
                let safeHSN  = $('<div>').text(res.hsn_code || '').html();
                let safeSys  = $('<div>').text(res.system_name || '').html();
                let safePoName = $('<div>').text(res.product_opening_name || '').html();
                let orderNoVal = parseInt(String(res.order_no || "0"), 10) || 0;

                <?php if ($masters_metal_has_opening_fields) { ?>
                var openingNameTd = `<td>${safePoName}</td>`;
                var orderTd = `<td class="text-center">${orderNoVal}</td>`;
                <?php } else { ?>
                var openingNameTd = '';
                var orderTd = '';
                <?php } ?>

                <?php if ($masters_metal_show_dash) { ?>
                var dashTd = (typeof res.show_on_dashboard !== 'undefined'
                    && (parseInt(res.show_on_dashboard, 10) === 1 || String(res.show_on_dashboard) === '1'))
                    ? '<td class="text-center"><span class="text-success">Yes</span></td>'
                    : '<td class="text-center"><span class="text-muted">—</span></td>';
                <?php } else { ?>
                var dashTd = '';
                <?php } ?>

                <?php if ($masters_metal_has_dash_img) { ?>
                var thumbSrc = "";
                if (res.dashboard_image_path) { thumbSrc = String(res.dashboard_image_path); }
                else if (res.dashboard_image_url) { thumbSrc = String(res.dashboard_image_url); }
                var thumbEsc = thumbSrc.replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;");
                var thumbTd = thumbSrc
                    ? `<td class="text-center align-middle p-1"><img src="${thumbEsc}" alt="" style="max-width:36px;max-height:36px;object-fit:contain;"></td>`
                    : `<td class="text-center align-middle p-1"><span class="text-muted">—</span></td>`;
                <?php } else { ?>
                var thumbTd = '';
                <?php } ?>

                let row = `
                    <tr id="metal_${res.id}">
                        <td>${safeName}</td>
                        <td>${safeHSN}</td>
                        <td>${safeSys}</td>
                        ${openingNameTd}
                        ${orderTd}
                        ${dashTd}
                        ${thumbTd}
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editMetal(
                                   ${res.id},
                                   '${safeName.replace(/'/g, "\\'")}',
                                   '${safeHSN.replace(/'/g, "\\'")}',
                                   '${safeSys.replace(/'/g, "\\'")}',
                                   '${safePoName.replace(/'/g, "\\'")}',
                                   ${orderNoVal}
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteMetal(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                id ? $("#metal_"+res.id).replaceWith(row)
                   : $("#metalTableBody").prepend(row);

                $("#metalForm")[0].reset();
                resetMetalImageFields();
                $("#metalId").val('');
                $("#metalModal").modal("hide");

            }else{
                alert(res.message || "Operation failed");
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    };

    if (useFormData) {
        let fd = new FormData();
        fd.append("action", id ? "update" : "add");
        if (id) { fd.append("id", String(id)); }
        fd.append("display_name", name);
        fd.append("hsn_code", hsn);
        fd.append("system_name", system);
        if ($("#metalProductOpeningName").length) {
            fd.append("product_opening_name", productOpeningName);
            fd.append("order_no", String(orderNo));
        }
        if ($("#metalImageUrl").length) {
            fd.append("dashboard_image_url", $("#metalImageUrl").val().trim());
            if ($("#metalImageClear").is(":checked")) {
                fd.append("clear_dashboard_image", "1");
            }
            let finp = $("#metalImageFile")[0];
            if (finp && finp.files && finp.files[0]) {
                fd.append("dashboard_image", finp.files[0]);
            }
        }
        if ($("#metalShowOnDashboard").length) {
            fd.append("show_on_dashboard", $("#metalShowOnDashboard").is(":checked") ? "1" : "0");
        }
        ajaxOpts.data = fd;
        ajaxOpts.processData = false;
        ajaxOpts.contentType = false;
    } else {
        ajaxOpts.data = {
            action: id ? "update" : "add",
            id: id,
            display_name: name,
            hsn_code: hsn,
            system_name: system,
            product_opening_name: productOpeningName,
            order_no: orderNo,
            show_on_dashboard: ($("#metalShowOnDashboard").length && $("#metalShowOnDashboard").is(":checked")) ? "1" : "0"
        };
    }

    $.ajax(ajaxOpts);
}

function deleteMetal(id){

    if(!confirm("Delete this metal?")) return;

    $.ajax({
        url: "ajax/metal.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "delete",
            id: id
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){
                $("#metal_" + id).remove();

                if($("#metalTableBody tr").length === 0){
                    $("#metalTableBody").html(`
                        <tr id="noMetalRow">
                            <td colspan="<?php echo (int) $masters_metal_table_cols; ?>" class="text-center text-muted">
                                No Metal Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        error: function(xhr){
            console.error(xhr.responseText);
            alert("Server error");
        },

        complete: hideLoader
    });
}

function openClarityModal(){
    $("#clarityModal").modal("show");
    $("#clarityId").val();
    $("#clarityName").val();
    $("#clarityModal .modal-title").text("Add Clarity");
}

function editClarity(id,name){
    $("#clarityId").val(id);
    $("#clarityName").val(name);
    $("#clarityModal .modal-title").text("Edit Clarity");
    $("#clarityModal").modal("show");
}

function saveClarity(){

    let id   = $("#clarityId").val();
    let name = $("#clarityName").val().trim();

    if(name === ""){
        alert("Name required");
        return;
    }

    $.ajax({
        url: "ajax/clarity.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: function(){
            showLoader();
        },

        success: function(res){

            if(res.status === "success"){

                $("#noClarityRow").remove();

                // ✅ safer escaping
                let safeName = $('<div>').text(res.name).html();

                let row = `
                    <tr id="clarity_${res.id}">
                        <td>${safeName}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               class="text-primary mr-2"
                               onclick="editClarity(${res.id}, '${safeName}')">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               class="text-danger"
                               onclick="deleteClarity(${res.id})">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#clarity_" + res.id).replaceWith(row);
                }else{
                    $("#clarityTableBody").prepend(row);
                }

                $("#clarityForm")[0].reset();
                $("#clarityId").val('');
                $("#clarityModal").modal("hide");

            }else{
                alert(res.message || "Operation failed");
            }
        },

        error: function(xhr){
            console.error("AJAX Error:", xhr.responseText);
            alert("Server error – check console");
        },

        complete: function(){
            hideLoader();
        }
    });
}

function deleteClarity(id){

    if(!confirm("Delete?")) return;

    $.ajax({
        url: "ajax/clarity.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "delete",
            id: id
        },

        beforeSend: function(){
            showLoader();
        },

        success: function(res){
            if(res.status === "success"){
                $("#clarity_" + id).remove();

                if($("#clarityTableBody tr").length === 0){
                    $("#clarityTableBody").html(`
                        <tr id="noClarityRow">
                            <td colspan="2" class="text-center text-muted">
                                No Clarity Found
                            </td>
                        </tr>
                    `);
                }
            }else{
                alert(res.message);
            }
        },

        error: function(xhr){
            console.error("AJAX Error:", xhr.responseText);
            alert("Server error – check console");
        },

        complete: function(){
            hideLoader();
        }
    });
}


function openUnitConvModal(){
    $("#unitConvForm")[0].reset();
    $("#unitConvId").val('');
    $("#unitConvModal .modal-title").text("Add Unit Conversion");
    $("#unitConvModal").modal("show");
}

function editUnitConversion(id,name,unit,rate,qty){
    $("#unitConvId").val(id);
    $("#unitConvName").val(name);
    $("#unitConvUnit").val(unit);
    $("#unitConvRate").val(rate);
    $("#unitConvQty").val(qty);
    $("#unitConvModal .modal-title").text("Edit Unit Conversion");
    $("#unitConvModal").modal("show");
}

function saveUnitConversion(){

    let id   = $("#unitConvId").val();
    let name = $("#unitConvName").val().trim();
    let unit = $("#unitConvUnit").val();
    let rate = $("#unitConvRate").val();
    let qty  = $("#unitConvQty").val();

    if(name=='' || unit=='' || rate=='' || qty==''){
        alert("All fields required");
        return;
    }

    $.ajax({
        url: "ajax/unit-conversion.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            unit_id: unit,
            conversion_rate: rate,
            quantity: qty
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status === "success"){

                $("#noUnitConvRow").remove();

                // get unit text from dropdown
                let unitText = $("#unitConvUnit option:selected").text();

                let safeName = res.name.replace(/'/g,"\\'");

                let row = `
                    <tr id="unitConv_${res.id}">
                        <td>${res.name}</td>
                        <td>${unitText}</td>
                        <td>${res.conversion_rate}</td>
                        <td>${res.quantity}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editUnitConversion(
                                   ${res.id},
                                   '${safeName}',
                                   '${res.unit_id}',
                                   '${res.conversion_rate}',
                                   '${res.quantity}'
                               )"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteUnitConversion(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#unitConv_"+res.id).replaceWith(row);
                }else{
                    $("#unitConvTableBody").prepend(row);
                }

                $("#unitConvModal").modal("hide");
                $("#unitConvForm")[0].reset();
                $("#unitConvId").val('');

            }else{
                alert(res.message || "Something went wrong");
            }
        },

        error: function(){
            alert("Server error");
        },

        complete: hideLoader
    });
}


function deleteUnitConversion(id){

    if(!confirm("Delete this conversion?")) return;

    $.ajax({
        url: "ajax/unit-conversion.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "delete",
            id: id
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status==="success"){
                $("#unitConv_"+id).remove();
            }
        },

        complete: hideLoader
    });
}

function openRemarkModal(){
    $("#remarkForm")[0].reset();
    $("#remarkId").val('');
    $("#remarkModal .modal-title").text("Add Remark");
    $("#remarkModal").modal("show");
}

function editRemark(id, name){
    $("#remarkId").val(id);
    $("#remarkName").val(name);
    $("#remarkModal .modal-title").text("Edit Remark");
    $("#remarkModal").modal("show");
}

function saveRemark(){

    let id   = $("#remarkId").val();
    let name = $("#remarkName").val().trim();

    if(name===''){
        alert("Remark name required");
        return;
    }

    $.ajax({
        url: "ajax/remark.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status==="success"){

                $("#noRemarkRow").remove();

                let safeName = res.name.replace(/'/g,"\\'");

                let row = `
                    <tr id="remark_${res.id}">
                        <td>${res.name}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editRemark(${res.id}, '${safeName}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>
                            <a href="javascript:void(0)"
                               onclick="deleteRemark(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#remark_"+res.id).replaceWith(row);
                }else{
                    $("#remarkTableBody").prepend(row);
                }

                $("#remarkModal").modal("hide");
                $("#remarkForm")[0].reset();
                $("#remarkId").val('');

            }else{
                alert(res.message);
            }
        },

        complete: hideLoader
    });
}

function deleteRemark(id){

    if(!confirm("Delete this remark?")) return;

    $.ajax({
        url: "ajax/remark.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "delete",
            id: id
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status==="success"){
                $("#remark_"+id).remove();

                if($("#remarkTableBody tr").length===0){
                    $("#remarkTableBody").html(`
                        <tr id="noRemarkRow">
                            <td colspan="2" class="text-center text-muted">
                                No Remark Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}

function openUnitModal(){
    $("#unitForm")[0].reset();
    $("#unitId").val('');
    $("#unitModal .modal-title").text("Add Unit");
    $("#unitModal").modal("show");
}

function editUnit(id, name, formal){
    $("#unitId").val(id);
    $("#unitName").val(name);
    $("#unitFormal").val(formal);
    $("#unitModal .modal-title").text("Edit Unit");
    $("#unitModal").modal("show");
}

function saveUnit(){

    let id     = $("#unitId").val();
    let name   = $("#unitName").val().trim();
    let formal = $("#unitFormal").val().trim();

    if(name=='' || formal==''){
        alert("All fields required");
        return;
    }

    $.ajax({
        url: "ajax/unit.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            formal_name: formal
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status==="success"){

                $("#noUnitRow").remove();

                let safeName   = res.name.replace(/'/g,"\\'");
                let safeFormal = res.formal_name.replace(/'/g,"\\'");

                let row = `
                    <tr id="unit_${res.id}">
                        <td>${res.name}</td>
                        <td>${res.formal_name}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editUnit(${res.id},
                               '${safeName}',
                               '${safeFormal}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>
                            <a href="javascript:void(0)"
                               onclick="deleteUnit(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#unit_"+res.id).replaceWith(row);
                }else{
                    $("#unitTableBody").prepend(row);
                }

                $("#unitModal").modal("hide");
                $("#unitForm")[0].reset();
                $("#unitId").val('');

            }else{
                alert(res.message);
            }
        },

        complete: hideLoader
    });
}

function deleteUnit(id){

    if(!confirm("Delete this unit?")) return;

    $.ajax({
        url: "ajax/unit.php",
        type: "POST",
        dataType: "json",
        data: {
            action: "delete",
            id: id
        },

        beforeSend: showLoader,

        success: function(res){
            if(res.status==="success"){
                $("#unit_"+id).remove();

                if($("#unitTableBody tr").length===0){
                    $("#unitTableBody").html(`
                        <tr id="noUnitRow">
                            <td colspan="3" class="text-center text-muted">
                                No Unit Found
                            </td>
                        </tr>
                    `);
                }
            }
        },

        complete: hideLoader
    });
}

function openCollectionModal(){
    $("#collectionForm")[0].reset();
    $("#collectionId").val('');
    $("#collectionModal .modal-title").text("Add Collection");
    $("#collectionModal").modal("show");
}

function editCollection(id, name, desc){
    $("#collectionId").val(id);
    $("#collectionName").val(name);
    $("#collectionDesc").val(desc);
    $("#collectionModal .modal-title").text("Edit Collection");
    $("#collectionModal").modal("show");
}

function saveCollection(){

    let id   = $("#collectionId").val();
    let name = $("#collectionName").val().trim();
    let desc = $("#collectionDesc").val().trim();

    if(name === ""){
        alert("Collection name required");
        return;
    }

    let url = id ? "ajax/collection.php" : "ajax/collection.php";

    jQuery.ajax({
        url: url,
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            description: desc
        },

        beforeSend: function(){
            showLoader();
        },

        success: function(res){
            if(res.status === "success"){

                $("#noCollectionRow").remove();

                let safeName = res.name.replace(/'/g,"\\'");
                let safeDesc = res.description.replace(/'/g,"\\'");

                let row = `
                    <tr id="collection_${res.id}">
                        <td>${res.name}</td>
                        <td>${res.description || '-'}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCollection(${res.id},
                               '${safeName}',
                               '${safeDesc}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteCollection(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#collection_"+res.id).replaceWith(row);
                }else{
                    $("#collectionTableBody").prepend(row);
                }

                $("#collectionModal").modal("hide");
                $("#collectionForm")[0].reset();
                $("#collectionId").val('');

            }else{
                alert(res.message);
            }
        },

        error: function(){
            alert("Server error");
        },

        complete: function(){
            hideLoader();
        }
    });
}

function deleteCollection(id){

    if(!confirm("Delete this collection?")) return;

    jQuery.ajax({
        url: "ajax/collection.php",
        type: "POST",
        dataType: "json",
        data: { action: "delete",id:id },

        beforeSend: function(){
            showLoader();
        },

        success: function(res){
            if(res.status === "success"){
                $("#collection_"+id).remove();

                if($("#collectionTableBody tr").length === 0){
                    $("#collectionTableBody").html(`
                        <tr id="noCollectionRow">
                            <td colspan="3" class="text-center text-muted">
                                No Collection Found
                            </td>
                        </tr>
                    `);
                }
            }else{
                alert(res.message);
            }
        },

        complete: function(){
            hideLoader();
        }
    });
}

function saveLocation(){

    let id   = $("#locationId").val();
    let name = $("#locationName").val().trim();

    if(name === ""){
        alert("Location name required");
        return;
    }

    let url = id ? "ajax/location.php" : "ajax/location.php";

    jQuery.ajax({
        url: url,
        type: "POST",
        dataType: "json",
        data: { action: id ? "update" : "add",id: id, name: name },

        beforeSend: function(){
            showLoader();
        },
        success: function(res){

            if(res.status === "success"){

                // remove "No Location Found" row if exists
                $("#noLocationRow").remove();

                let row = `
                    <tr id="location_${res.id}">
                        <td>${res.name}</td>
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editLocation(${res.id}, '${res.name.replace(/'/g, "\\'")}')"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>

                            <a href="javascript:void(0)"
                               onclick="deleteLocation(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#location_" + res.id).replaceWith(row);
                } else {
                    $("#locationTableBody").prepend(row);
                }

                $("#locationForm")[0].reset();
                $("#locationId").val('');
                $("#locationModal").modal("hide");

            } else {
                alert(res.message || "Something went wrong");
            }
        },

        error: function(){
            alert("Server error. Please try again.");
        },

        complete: function(){
            hideLoader();
        }
    });
}

function editLocation(id, name){
    $("#locationId").val(id);
    $("#locationName").val(name);
    $("#locationModal .modal-title").text("Edit Location");
    $("#locationModal").modal("show");
}

function saveTaxMaster() {
    var id = $("#taxMasterId").val();
    var name = $("#taxMasterName").val().trim();
    var default_value = parseFloat($("#taxMasterDefaultValue").val()) || 0;
    var default_calculation_mode = $("#taxMasterCalculationMode").val() || "Product Amount";
    var gst_supply_scope = $("#taxMasterGstSupplyScope").val() || "local_state";
    var sort_order = parseInt($("#taxMasterSortOrder").val(), 10) || 0;
    if (name === "") {
        alert("Tax name is required");
        return;
    }
    jQuery.ajax({
        url: "ajax/tax-master.php",
        type: "POST",
        dataType: "json",
        data: {
            action: id ? "update" : "add",
            id: id,
            name: name,
            default_value: default_value,
            default_calculation_mode: default_calculation_mode,
            gst_supply_scope: gst_supply_scope,
            sort_order: sort_order
        },
        beforeSend: function() { showLoader(); },
        success: function(res) {
            if (res.status === "success") {
                $("#noTaxMasterRow").remove();
                var gstLabel = (res.gst_supply_scope === 'out_of_state') ? 'Out of state' : 'Local state';
                var gstEsc = (res.gst_supply_scope || 'local_state').replace(/'/g, "\\'");
                var row = '<tr id="taxMaster_' + res.id + '">' +
                    '<td>' + (res.name || '').replace(/</g, '&lt;') + '</td>' +
                    '<td>' + res.default_value + '</td>' +
                    '<td>' + (res.default_calculation_mode || '').replace(/</g, '&lt;') + '</td>' +
                    '<td>' + gstLabel + '</td>' +
                    '<td class="text-center">' +
                    '<a href="javascript:void(0)" onclick="editTaxMaster(' + res.id + ', \'' + (res.name || '').replace(/'/g, "\\'") + '\', \'' + res.default_value + '\', \'' + (res.default_calculation_mode || '').replace(/'/g, "\\'") + '\', ' + res.sort_order + ', \'' + gstEsc + '\')" class="text-primary mr-2"><i class="feather icon-edit"></i></a> ' +
                    '<a href="javascript:void(0)" onclick="deleteTaxMaster(' + res.id + ')" class="text-danger"><i class="feather icon-trash-2"></i></a>' +
                    '</td></tr>';
                if (id) {
                    $("#taxMaster_" + res.id).replaceWith(row);
                } else {
                    $("#taxMasterTableBody").prepend(row);
                }
                $("#taxMasterForm")[0].reset();
                $("#taxMasterId").val("");
                $("#taxMasterModal .modal-title").text("Add Tax");
                $("#taxMasterModal").modal("hide");
            } else {
                alert(res.message || "Something went wrong");
            }
        },
        error: function() { alert("Server error. Please try again."); },
        complete: function() { hideLoader(); }
    });
}
function editTaxMaster(id, name, default_value, default_calculation_mode, sort_order, gst_supply_scope) {
    $("#taxMasterId").val(id);
    $("#taxMasterName").val(name);
    $("#taxMasterDefaultValue").val(default_value);
    $("#taxMasterCalculationMode").val(default_calculation_mode || "Product Amount");
    $("#taxMasterGstSupplyScope").val(gst_supply_scope === 'out_of_state' ? 'out_of_state' : 'local_state');
    $("#taxMasterSortOrder").val(sort_order || 0);
    $("#taxMasterModal .modal-title").text("Edit Tax");
    $("#taxMasterModal").modal("show");
}
function deleteTaxMaster(id) {
    if (!confirm("Delete this tax?")) return;
    jQuery.ajax({
        url: "ajax/tax-master.php",
        type: "POST",
        dataType: "json",
        data: { action: "delete", id: id },
        beforeSend: function() { showLoader(); },
        success: function(res) {
            if (res.status === "success") {
                $("#taxMaster_" + id).remove();
                if ($("#taxMasterTableBody tr").length === 0) {
                    $("#taxMasterTableBody").html('<tr id="noTaxMasterRow"><td colspan="5" class="text-center text-muted">No Tax Found. Run sql/create_tbl_tax_master.sql then add taxes.</td></tr>');
                }
            } else {
                alert(res.message || "Error");
            }
        },
        error: function() { alert("Server error."); },
        complete: function() { hideLoader(); }
    });
}

function deleteLocation(id){

    if(!confirm("Delete this location?")) return;

    jQuery.ajax({
        url: "ajax/location.php",
        type: "POST",
        dataType: "json",
        data: { action: "delete",id: id },

        beforeSend: function(){
            showLoader();
        },

        success: function(res){
            if(res.status === "success"){
                $("#location_" + id).remove();

                if($("#locationTableBody tr").length === 0){
                    $("#locationTableBody").html(`
                        <tr id="noLocationRow">
                            <td colspan="2" class="text-center text-muted">
                                No Location Found
                            </td>
                        </tr>
                    `);
                }
            }else{
                alert(res.message);
            }
        },

        error: function(){
            alert("Server error.");
        },

        complete: function(){
            hideLoader();
        }
    });
}
function resetCaratImageFields() {
    if (!$("#caratImageUrl").length) {
        return;
    }
    $("#caratImageFile").val("");
    $("#caratImageUrl").val("");
    $("#caratImageClear").prop("checked", false);
    $("#caratImagePreview").hide().attr("src", "");
}

function loadCaratImageForEdit(id) {
    if (!$("#caratImageUrl").length) {
        return;
    }
    $("#caratImageFile").val("");
    $("#caratImageClear").prop("checked", false);
    $("#caratImagePreview").hide().attr("src", "");
    var nid = parseInt(id, 10);
    if (!nid || nid <= 0) {
        $("#caratImageUrl").val("");
        return;
    }
    jQuery.getJSON("ajax/carat.php", { action: "get", id: nid })
        .done(function (res) {
            if (!res || res.status !== "success" || !res.row) {
                $("#caratImageUrl").val("");
                return;
            }
            var r = res.row;
            if ($("#caratPuritySales").length) {
                $("#caratPuritySales").val(r.purity_sales != null && r.purity_sales !== "" ? r.purity_sales : "");
                $("#caratPurityPurchase").val(r.purity_purchase != null && r.purity_purchase !== "" ? r.purity_purchase : "");
                $("#caratPurityCommon").val(r.purity_common != null && r.purity_common !== "" ? r.purity_common : "");
            }
            $("#caratImageUrl").val(r.dashboard_image_url || "");
            var src = (r.dashboard_image_path || r.dashboard_image_url || "").trim();
            if (src) {
                $("#caratImagePreview").attr("src", src).show();
            }
        });
}

function editCarat(id, name, desc, metalId, puritySales, purityPurchase, purityCommon) {

    $("#caratId").val(id);
    $("#caratName").val(name);
    $("#caratDesc").val(desc);
    if ($("#caratPuritySales").length) {
        $("#caratPuritySales").val(puritySales != null ? puritySales : "");
        $("#caratPurityPurchase").val(purityPurchase != null ? purityPurchase : "");
        $("#caratPurityCommon").val(purityCommon != null ? purityCommon : "");
    } else if ($("#caratPurity").length) {
        $("#caratPurity").val(puritySales != null ? puritySales : "");
    }
    if ($("#caratMetalId").length) {
        var mid = (metalId != null && metalId !== "" && parseInt(metalId, 10) > 0) ? String(parseInt(metalId, 10)) : "1";
        if ($("#caratMetalId option[value='" + mid + "']").length) {
            $("#caratMetalId").val(mid);
        } else {
            $("#caratMetalId").prop("selectedIndex", 0);
        }
    }

    loadCaratImageForEdit(id);

    $("#caratModal .modal-title").text("Edit Carat");
    $("#caratModal").modal("show");
}

function saveCarat(){

    let id     = $("#caratId").val();
    let name   = $("#caratName").val().trim();
    let desc   = $("#caratDesc").val().trim();
    let purity = "";
    let puritySales = "";
    let purityPurchase = "";
    let purityCommon = "";
    if ($("#caratPuritySales").length) {
        puritySales = $("#caratPuritySales").val().trim();
        purityPurchase = $("#caratPurityPurchase").val().trim();
        purityCommon = $("#caratPurityCommon").val().trim();
        purity = purityCommon || puritySales || purityPurchase;
    } else {
        purity = $("#caratPurity").val().trim();
        purityCommon = purity;
    }

    if(name === ""){
        alert("Carat name is required");
        return;
    }
    if ($("#caratMetalId").length) {
        let mid = $("#caratMetalId").val();
        if (mid === "" || mid === null) {
            alert("Metal is required");
            return;
        }
    }
    if ($("#caratPuritySales").length) {
        if (puritySales === "") {
            alert("Sale Purity % is required");
            return;
        }
        if (purityPurchase === "") {
            alert("Purchase Purity % is required");
            return;
        }
    }

    let useFormData = $("#caratImageUrl").length > 0;
    let ajaxOpts = {
        url: "ajax/carat.php",
        type: "POST",
        dataType: "json",
        beforeSend: function(){
            showLoader();
        },
        success: function(res){
            if(res.status === "success"){

                $("#noCaratRow").remove();

                let safeName = res.name.replace(/'/g, "\\'");
                let safeDesc = res.description.replace(/'/g, "\\'");
                let metalTd = <?php echo $masters_carat_has_metal ? "true" : "false"; ?>
                    ? `<td>${res.metal_name ? res.metal_name : '—'}</td>` : '';
                let splitPurityTds = <?php echo $masters_carat_has_split_purity ? "true" : "false"; ?>
                    ? `<td>${res.purity_sales || '-'}</td><td>${res.purity_purchase || '-'}</td><td>${res.purity_common || '-'}</td>` : '';
                let safePuritySales = (res.purity_sales != null) ? String(res.purity_sales).replace(/'/g, "\\'") : "";
                let safePurityPurchase = (res.purity_purchase != null) ? String(res.purity_purchase).replace(/'/g, "\\'") : "";
                let safePurityCommon = (res.purity_common != null) ? String(res.purity_common).replace(/'/g, "\\'") : "";

                <?php if ($masters_carat_has_dash_img) { ?>
                var thumbSrc = "";
                if (res.dashboard_image_path) { thumbSrc = String(res.dashboard_image_path); }
                else if (res.dashboard_image_url) { thumbSrc = String(res.dashboard_image_url); }
                var thumbEsc = thumbSrc.replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;");
                var thumbTd = thumbSrc
                    ? `<td class="text-center align-middle p-1"><img src="${thumbEsc}" alt="" style="max-width:36px;max-height:36px;object-fit:contain;"></td>`
                    : `<td class="text-center align-middle p-1"><span class="text-muted">—</span></td>`;
                <?php } else { ?>
                var thumbTd = '';
                <?php } ?>

                let row = `
                    <tr id="carat_${res.id}">
                        <td>${res.name}</td>
                        ${metalTd}
                        <?php if ($masters_carat_has_split_purity) { ?>
                        ${splitPurityTds}
                        <?php } else { ?>
                        <td>${res.purity || '-'}</td>
                        <?php } ?>
                        <td>${res.description || '-'}</td>
                        ${thumbTd}
                        <td class="text-center">
                            <a href="javascript:void(0)"
                               onclick="editCarat(${res.id},
                               '${safeName}',
                               '${safeDesc}'<?php if ($masters_carat_has_metal) { ?>,
                               ${typeof res.metal_id !== 'undefined' ? res.metal_id : 0}<?php } ?><?php if ($masters_carat_has_split_purity) { ?>,
                               '${safePuritySales}',
                               '${safePurityPurchase}',
                               '${safePurityCommon}'<?php } else { ?>,
                               '${(res.purity || '').replace(/'/g, "\\'")}'<?php } ?>)"
                               class="text-primary mr-2">
                                <i class="feather icon-edit"></i>
                            </a>
                            <a href="javascript:void(0)"
                               onclick="deleteCarat(${res.id})"
                               class="text-danger">
                                <i class="feather icon-trash-2"></i>
                            </a>
                        </td>
                    </tr>
                `;

                if(id){
                    $("#carat_" + res.id).replaceWith(row);
                }else{
                    $("#caratTableBody").prepend(row);
                }

                $("#caratForm")[0].reset();
                resetCaratImageFields();
                $("#caratModal").modal("hide");

            }else{
                alert(res.message);
            }
        },

        error: function(){
            alert("Server error.");
        },

        complete: function(){
            hideLoader();
        }
    };

    if (useFormData) {
        let fd = new FormData();
        fd.append("action", id ? "update" : "add");
        if (id) { fd.append("id", String(id)); }
        fd.append("name", name);
        fd.append("purity", purity);
        fd.append("description", desc);
        if ($("#caratPuritySales").length) {
            fd.append("purity_sales", puritySales);
            fd.append("purity_purchase", purityPurchase);
            fd.append("purity_common", purityCommon);
        }
        if ($("#caratMetalId").length) {
            fd.append("metal_id", $("#caratMetalId").val());
        }
        fd.append("dashboard_image_url", $("#caratImageUrl").val().trim());
        if ($("#caratImageClear").is(":checked")) {
            fd.append("clear_dashboard_image", "1");
        }
        let finp = $("#caratImageFile")[0];
        if (finp && finp.files && finp.files[0]) {
            fd.append("dashboard_image", finp.files[0]);
        }
        ajaxOpts.data = fd;
        ajaxOpts.processData = false;
        ajaxOpts.contentType = false;
    } else {
        ajaxOpts.data = {
            action: id ? "update" : "add",
            id: id,
            name: name,
            purity: purity,
            description: desc
        };
        if ($("#caratPuritySales").length) {
            ajaxOpts.data.purity_sales = puritySales;
            ajaxOpts.data.purity_purchase = purityPurchase;
            ajaxOpts.data.purity_common = purityCommon;
        }
        if ($("#caratMetalId").length) {
            ajaxOpts.data.metal_id = $("#caratMetalId").val();
        }
    }

    jQuery.ajax(ajaxOpts);
}

function deleteCarat(id){

    if(!confirm("Are you sure you want to delete this carat?")) return;

    jQuery.ajax({
        url: "ajax/carat.php",
        type: "POST",
        dataType: "json",
        data: { action: "delete",id: id },

        beforeSend: function(){
            showLoader();
        },

        success: function(res){
            if(res.status === "success"){
                $("#carat_" + id).remove();

                if($("#caratTableBody tr").length === 0){
                    $("#caratTableBody").html(`
                        <tr id="noCaratRow">
                            <td colspan="<?php echo (int) $masters_carat_colspan; ?>" class="text-center text-muted">
                                No Carat Found
                            </td>
                        </tr>
                    `);
                }
            }else{
                alert(res.message);
            }
        },

        error: function(){
            alert("Server error.");
        },

        complete: function(){
            hideLoader();
        }
    });
}
</script>

<script>
    // Add keyboard shortcut for Add Item
    document.addEventListener('keydown', function(e) {
        if (e.shiftKey && e.key === 'Q') {
            e.preventDefault();
            // Add item functionality here
            console.log('Add Item triggered');
        }
    });

    // Table Settings - Column Visibility Toggle
    (function() {
        const settingsBtn = document.getElementById('tableSettingsBtn');
        const settingsDropdown = document.getElementById('tableSettingsDropdown');
        if (!settingsBtn || !settingsDropdown) return;

        const checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"]');
        
        // Toggle dropdown on button click
        settingsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            settingsDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!settingsBtn.contains(e.target) && !settingsDropdown.contains(e.target)) {
                settingsDropdown.classList.remove('show');
            }
        });

        // Handle column visibility
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const columnName = this.getAttribute('data-column');
                const isVisible = this.checked;
                
                // Toggle visibility for all th and td elements with this column
                const headers = document.querySelectorAll(`.product-table th[data-column="${columnName}"]`);
                const cells = document.querySelectorAll(`.product-table td[data-column="${columnName}"]`);
                
                headers.forEach(function(header) {
                    if (isVisible) {
                        header.classList.remove('hidden');
                    } else {
                        header.classList.add('hidden');
                    }
                });
                
                cells.forEach(function(cell) {
                    if (isVisible) {
                        cell.classList.remove('hidden');
                    } else {
                        cell.classList.add('hidden');
                    }
                });

                // Update colspan for empty state row
                const emptyRowCell = document.getElementById('emptyRowCell');
                if (emptyRowCell) {
                    const visibleColumns = Array.from(checkboxes).filter(cb => cb.checked).length;
                    // Add 1 for drag handle column (always visible)
                    emptyRowCell.setAttribute('colspan', visibleColumns + 1);
                }
            });
        });
    })();

    // Column Drag and Drop Functionality
    (function() {
        const table = document.querySelector('.product-table');
        if (!table) return;
        
        const thead = table.querySelector('thead tr');
        const tbody = document.getElementById('productTableBody');
        if (!thead || !tbody) return;
        
        let draggedColumn = null;
        let draggedColumnIndex = null;
        let dragOverColumn = null;
        let dragOverPosition = null;

        // Get all draggable column headers
        function getDraggableColumns() {
            return thead.querySelectorAll('th.draggable-column');
        }

        // Get column index from header
        function getColumnIndex(th) {
            return Array.from(thead.children).indexOf(th);
        }

        // Reorder columns in all rows
        function reorderColumns(dragIndex, dropIndex) {
            const allRows = [thead, ...Array.from(tbody.querySelectorAll('tr'))];
            
            allRows.forEach(row => {
                const cells = Array.from(row.children);
                const draggedCell = cells[dragIndex];
                
                if (draggedCell) {
                    cells.splice(dragIndex, 1);
                    cells.splice(dropIndex, 0, draggedCell);
                    
                    // Reorder in DOM
                    cells.forEach(cell => row.appendChild(cell));
                }
            });
        }

        // Initialize column drag and drop
        function initColumnDragAndDrop() {
            const columns = getDraggableColumns();
            
            columns.forEach(th => {
                th.addEventListener('dragstart', function(e) {
                    draggedColumn = th;
                    draggedColumnIndex = getColumnIndex(th);
                    th.classList.add('dragging-column');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', ''); // Required for Firefox
                    e.stopPropagation();
                }, false);

                th.addEventListener('dragend', function(e) {
                    if (draggedColumn) {
                        draggedColumn.classList.remove('dragging-column');
                    }
                    // Remove all drag-over classes
                    getDraggableColumns().forEach(col => {
                        col.classList.remove('drag-over-column', 'drag-over-column-right');
                    });
                    draggedColumn = null;
                    draggedColumnIndex = null;
                    dragOverColumn = null;
                    dragOverPosition = null;
                }, false);

                th.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.dataTransfer.dropEffect = 'move';

                    if (!draggedColumn || th === draggedColumn) {
                        return;
                    }

                    // Remove previous drag-over classes
                    getDraggableColumns().forEach(col => {
                        col.classList.remove('drag-over-column', 'drag-over-column-right');
                    });

                    // Calculate position (left or right half of column)
                    const rect = th.getBoundingClientRect();
                    const mouseX = e.clientX;
                    const colMiddle = rect.left + rect.width / 2;

                    if (mouseX < colMiddle) {
                        th.classList.add('drag-over-column');
                        dragOverColumn = th;
                        dragOverPosition = 'left';
                    } else {
                        th.classList.add('drag-over-column-right');
                        dragOverColumn = th;
                        dragOverPosition = 'right';
                    }
                }, false);

                th.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (!draggedColumn || !dragOverColumn || dragOverColumn === draggedColumn) {
                        return;
                    }

                    const dropIndex = getColumnIndex(dragOverColumn);
                    const dragIndex = draggedColumnIndex;

                    // Remove drag-over classes
                    getDraggableColumns().forEach(col => {
                        col.classList.remove('drag-over-column', 'drag-over-column-right');
                    });

                    // Calculate final drop position
                    let finalDropIndex = dropIndex;
                    if (dragOverPosition === 'right' && dragIndex < dropIndex) {
                        finalDropIndex = dropIndex + 1;
                    } else if (dragOverPosition === 'left' && dragIndex > dropIndex) {
                        finalDropIndex = dropIndex;
                    } else if (dragOverPosition === 'right' && dragIndex > dropIndex) {
                        finalDropIndex = dropIndex + 1;
                    } else {
                        finalDropIndex = dropIndex;
                    }

                    // Reorder columns
                    reorderColumns(dragIndex, finalDropIndex);
                    
                    // Reset
                    draggedColumn = null;
                    draggedColumnIndex = null;
                    dragOverColumn = null;
                    dragOverPosition = null;
                }, false);
            });
        }

        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initColumnDragAndDrop();
            });
        } else {
            initColumnDragAndDrop();
        }

        // Add sample rows for testing
        setTimeout(function() {
            const hasRealRows = tbody.querySelectorAll('tr:not(.no-drag)').length > 0;
            if (!hasRealRows) {
                const testRows = [
                    ['BAR001', 'Gold Ring', '1', '5.5', '5.0', '4.8', '4.5', '500', '50', '5000', '5500'],
                    
                    ['BAR003', 'Diamond Earrings', '2', '3.5', '3.2', '3.0', '2.8', '1200', '120', '12000', '13320']
                ];
                
                const emptyRow = tbody.querySelector('.no-drag');
                if (emptyRow) {
                    emptyRow.remove();
                }
                
                testRows.forEach((rowData) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td></td>
                        <td data-column="barcode">${rowData[0]}</td>
                        <td data-column="description">${rowData[1]}</td>
                        <td data-column="quantity">${rowData[2]}</td>
                        <td data-column="gross-wt">${rowData[3]}</td>
                        <td data-column="final-wt">${rowData[4]}</td>
                        <td data-column="net-wt">${rowData[5]}</td>
                        <td data-column="pure-wt">${rowData[6]}</td>
                        <td data-column="making">${rowData[7]}</td>
                        <td data-column="tax">${rowData[8]}</td>
                        <td data-column="amount">${rowData[9]}</td>
                        <td data-column="net">${rowData[10]}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }, 500);
    })();

    // Fullscreen Toggle Functionality
    (function() {
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        if (!fullscreenBtn) return;

        const fullscreenIcon = fullscreenBtn.querySelector('i');
        if (!fullscreenIcon) return;

        // Function to toggle fullscreen
        function toggleFullscreen() {
            if (!document.fullscreenElement && !document.webkitFullscreenElement && 
                !document.mozFullScreenElement && !document.msFullscreenElement) {
                // Enter fullscreen
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) {
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.msRequestFullscreen) {
                    document.documentElement.msRequestFullscreen();
                }
            } else {
                // Exit fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }

        // Update icon based on fullscreen state
        function updateFullscreenIcon() {
            const isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement || 
                                   document.mozFullScreenElement || document.msFullscreenElement);
            
            if (isFullscreen) {
                fullscreenIcon.className = 'feather icon-minimize-2';
                fullscreenBtn.title = 'Exit Fullscreen';
            } else {
                fullscreenIcon.className = 'feather icon-maximize-2';
                fullscreenBtn.title = 'Fullscreen';
            }
        }

        // Add click event listener
        fullscreenBtn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleFullscreen();
        });

        // Listen for fullscreen changes
        document.addEventListener('fullscreenchange', updateFullscreenIcon);
        document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);
        document.addEventListener('mozfullscreenchange', updateFullscreenIcon);
        document.addEventListener('MSFullscreenChange', updateFullscreenIcon);

        // Also handle ESC key to exit fullscreen (browser default, but update icon)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                setTimeout(updateFullscreenIcon, 100);
            }
        });
    })();

    // Masters heading search
    (function() {
        const searchInput = document.getElementById('mastersSearchInput');
        const mastersGrid = document.getElementById('mastersGrid');
        const noResults = document.getElementById('mastersNoResults');
        const searchCount = document.getElementById('mastersSearchCount');

        if (!searchInput || !mastersGrid) return;

        const cards = mastersGrid.querySelectorAll('.master-card');
        const totalCards = cards.length;

        function filterMasters() {
            const term = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            cards.forEach(function(card) {
                const header = card.querySelector('.master-header');
                const heading = header ? header.textContent.trim().toLowerCase() : '';
                const match = term === '' || heading.indexOf(term) !== -1;

                card.classList.toggle('masters-hidden', !match);
                if (match) visibleCount++;
            });

            if (noResults) {
                noResults.classList.toggle('show', term !== '' && visibleCount === 0);
            }

            if (searchCount) {
                if (term === '') {
                    searchCount.textContent = totalCards + ' masters';
                } else {
                    searchCount.textContent = visibleCount + ' of ' + totalCards + ' shown';
                }
            }
        }

        searchInput.addEventListener('input', filterMasters);
        filterMasters();
    })();

    // User Dropdown Menu Toggle
    (function() {
        const userDropdownToggle = document.getElementById('userDropdownToggle');
        const userDropdown = document.querySelector('.user-dropdown');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        
        if (!userDropdownToggle || !userDropdown || !userDropdownMenu) return;

        // Toggle dropdown on click
        userDropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });

        // Close dropdown when clicking on a menu item
        const dropdownItems = userDropdownMenu.querySelectorAll('.dropdown-item');
        dropdownItems.forEach(function(item) {
            item.addEventListener('click', function(e) {
                // Allow the link to work, but close dropdown after a short delay
                setTimeout(function() {
                    userDropdown.classList.remove('show');
                }, 100);
            });
        });

        // Close dropdown on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && userDropdown.classList.contains('show')) {
                userDropdown.classList.remove('show');
            }
        });
    })();
</script>
</body>

</html>


