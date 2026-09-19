<?php
/**
 * Same master/branch scoping and defaults as product-opening.php for Add Product modal in common-modal.php.
 * Idempotent: safe to require once per request.
 */
if (empty($conn) || !($conn instanceof mysqli)) {
    return;
}
if (!empty($GLOBALS['auragold_product_add_form_shared_data_loaded'])) {
    return;
}
$GLOBALS['auragold_product_add_form_shared_data_loaded'] = true;

if (!function_exists('auragold_master_list_sql_suffix')) {
    $scopePath = __DIR__ . '/auragold_branch_data_scope.php';
    if (is_file($scopePath)) {
        require_once $scopePath;
    }
}

$auragold_sj_working_branch = 0;
if (isset($auragold_working_branch_id) && (int) $auragold_working_branch_id > 0) {
    $auragold_sj_working_branch = (int) $auragold_working_branch_id;
} elseif (function_exists('auragold_resolve_working_branch_id_for_page')) {
    $auragold_sj_working_branch = auragold_resolve_working_branch_id_for_page();
}
if ($auragold_sj_working_branch <= 0 && !empty($_SESSION['working_branch_id'])) {
    $auragold_sj_working_branch = (int) $_SESSION['working_branch_id'];
} elseif ($auragold_sj_working_branch <= 0 && !empty($_SESSION['branch_id'])) {
    $auragold_sj_working_branch = (int) $_SESSION['branch_id'];
}

$metals_sql_suffix = '';
if ($auragold_sj_working_branch > 0 && function_exists('auragold_master_list_sql_for_branch_id')) {
    $metals_sql_suffix = auragold_master_list_sql_for_branch_id($conn, 'tbl_metal', $auragold_sj_working_branch);
}
if ($metals_sql_suffix === '' && function_exists('auragold_settings_main_branch_id')) {
    $mbid = (int) auragold_settings_main_branch_id();
    if ($mbid > 0 && function_exists('auragold_master_list_sql_for_branch_id')) {
        $metals_sql_suffix = auragold_master_list_sql_for_branch_id($conn, 'tbl_metal', $mbid);
    }
}
if ($metals_sql_suffix === '' && function_exists('auragold_master_list_sql_suffix')) {
    $metals_sql_suffix = auragold_master_list_sql_suffix($conn, 'tbl_metal');
}

$metals_img_cols = '';
if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal', 'dashboard_image_path')) {
    $metals_img_cols = ', dashboard_image_path, dashboard_image_url';
}
$metals_list = getList("SELECT id, display_name, hsn_code" . $metals_img_cols . " FROM tbl_metal WHERE status = 1 " . $metals_sql_suffix . " ORDER BY id ASC");
if (!is_array($metals_list)) {
    $metals_list = [];
}
if (!function_exists('auragold_dashboard_resolve_dashboard_image_src')) {
    $dashImgPath = __DIR__ . '/auragold_dashboard_metal_images.php';
    if (is_file($dashImgPath)) {
        require_once $dashImgPath;
    }
}
if (!empty($metals_list) && function_exists('auragold_dashboard_resolve_dashboard_image_src')) {
    foreach ($metals_list as $mi => $mrow) {
        if (!is_array($mrow)) {
            continue;
        }
        $metals_list[$mi]['image_src'] = auragold_dashboard_resolve_dashboard_image_src(
            (string) ($mrow['dashboard_image_path'] ?? ''),
            (string) ($mrow['dashboard_image_url'] ?? '')
        );
    }
}
if (count($metals_list) > 1) {
    $seen_metal_tab = [];
    $metals_dedup = [];
    foreach ($metals_list as $mrow) {
        $dn = strtolower(trim((string) ($mrow['display_name'] ?? '')));
        if ($dn === '') {
            continue;
        }
        if (isset($seen_metal_tab[$dn])) {
            continue;
        }
        $seen_metal_tab[$dn] = true;
        $metals_dedup[] = $mrow;
    }
    $metals_list = $metals_dedup;
}

$tax_master_list = [];
$tax_master_sql = '';
if ($auragold_sj_working_branch > 0 && function_exists('auragold_master_list_sql_for_branch_id')) {
    $tax_master_sql = auragold_master_list_sql_for_branch_id($conn, 'tbl_tax_master', $auragold_sj_working_branch);
} elseif (function_exists('auragold_master_list_sql_for_working_branch')) {
    $tax_master_sql = auragold_master_list_sql_for_working_branch($conn, 'tbl_tax_master', $auragold_sj_working_branch);
} elseif (function_exists('auragold_master_list_sql_suffix')) {
    $tax_master_sql = auragold_master_list_sql_suffix($conn, 'tbl_tax_master');
}
if ($tax_master_sql !== '' || function_exists('getList')) {
    $tax_master_list = getList(
        "SELECT id, name, default_value, default_calculation_mode FROM tbl_tax_master WHERE status = 1 "
        . $tax_master_sql
        . " ORDER BY sort_order ASC, id ASC"
    );
}
if (!is_array($tax_master_list)) {
    $tax_master_list = [];
}
if (count($tax_master_list) > 1) {
    $seen_tax = [];
    $tax_dedup = [];
    foreach ($tax_master_list as $trow) {
        if (!is_array($trow)) {
            continue;
        }
        $tn = strtolower(trim((string) ($trow['name'] ?? '')));
        if ($tn === '' || isset($seen_tax[$tn])) {
            continue;
        }
        $seen_tax[$tn] = true;
        $tax_dedup[] = $trow;
    }
    $tax_master_list = $tax_dedup;
}

$locSqlSuffix = function_exists('auragold_master_list_sql_for_working_branch')
    ? auragold_master_list_sql_for_working_branch($conn, 'tbl_location', $auragold_sj_working_branch)
    : (function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_location') : '');
$unitSqlSuffix = function_exists('auragold_master_list_sql_for_working_branch')
    ? auragold_master_list_sql_for_working_branch($conn, 'tbl_unit', $auragold_sj_working_branch)
    : (function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_unit') : '');
$locations = getList("SELECT id, name FROM tbl_location WHERE status = 1 " . $locSqlSuffix . " ORDER BY id ASC");
$units = getList("SELECT id, name FROM tbl_unit WHERE status = 1 " . $unitSqlSuffix . " ORDER BY id ASC");
if (!is_array($locations)) {
    $locations = [];
}
if (!is_array($units)) {
    $units = [];
}

if (!isset($branches) || !is_array($branches) || empty($branches)) {
    $branches = [];
    if (function_exists('auragold_filter_branches_for_login_scope')) {
        $branches = auragold_filter_branches_for_login_scope();
    }
    if (empty($branches) && function_exists('getListMaster')) {
        $branches = getListMaster("SELECT id, name, code FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
    }
    if (!is_array($branches)) {
        $branches = [];
    }
    $branches = array_values(array_map(static function ($b) {
        return [
            'id'   => (int) ($b['id'] ?? 0),
            'name' => (string) ($b['name'] ?? ''),
            'code' => (string) ($b['code'] ?? ''),
        ];
    }, $branches));
}

$auragold_new_product_default_branch_id = 0;
$auragold_new_product_default_branch_name = '';
if (!empty($branches) && is_array($branches)) {
    $branch_ids_ok = array_map('intval', array_column($branches, 'id'));
    $bid = 0;
    if (!empty($_SESSION['working_branch_id'])) {
        $bid = (int) $_SESSION['working_branch_id'];
    } elseif (!empty($_SESSION['branch_id'])) {
        $bid = (int) $_SESSION['branch_id'];
    }
    if ($bid > 0 && in_array($bid, $branch_ids_ok, true)) {
        $auragold_new_product_default_branch_id = $bid;
    }
    if ($auragold_new_product_default_branch_id <= 0) {
        foreach ($branches as $branch) {
            $nl = strtolower((string) ($branch['name'] ?? ''));
            if (strpos($nl, 'main') !== false && strpos($nl, 'dubai') === false) {
                $auragold_new_product_default_branch_id = (int) $branch['id'];
                break;
            }
        }
    }
    if ($auragold_new_product_default_branch_id <= 0) {
        $auragold_new_product_default_branch_id = (int) $branches[0]['id'];
    }
    foreach ($branches as $branch) {
        if ((int) $branch['id'] === $auragold_new_product_default_branch_id) {
            $auragold_new_product_default_branch_name = (string) ($branch['name'] ?? '');
            break;
        }
    }
}

// Vendors (suppliers) for Add Product modal — same as product-opening.php
$vendors = [];
$supplier_type_row = getRecord(
    "SELECT id FROM tbl_customer_types WHERE status = 1 AND LOWER(TRIM(name)) IN ('supplier', 'vendor') ORDER BY id ASC LIMIT 1"
);
$supplier_type_id = (int) ($supplier_type_row['id'] ?? 0);
if ($supplier_type_id > 0) {
    $vendors = getList(
        "SELECT id, name FROM tbl_customers WHERE status = 1 AND customer_type_id = $supplier_type_id ORDER BY name ASC"
    );
} else {
    $vendors = getList(
        "SELECT id, name FROM tbl_customers WHERE status = 1 AND group_id = 2 ORDER BY name ASC"
    );
}
if (!is_array($vendors)) {
    $vendors = [];
}
