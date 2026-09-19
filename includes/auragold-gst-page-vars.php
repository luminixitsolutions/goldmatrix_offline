<?php
/**
 * Shared GST context for voucher/product pages (owner branch state, state name map, tax master JSON).
 * Include once after config.php / $conn is available.
 */
if (!empty($GLOBALS['auragold_gst_page_vars_loaded'])) {
    return;
}
$GLOBALS['auragold_gst_page_vars_loaded'] = true;

require_once __DIR__ . '/auragold-gst.php';
if (!function_exists('auragold_master_list_sql_suffix') && is_file(__DIR__ . '/auragold_branch_data_scope.php')) {
    require_once __DIR__ . '/auragold_branch_data_scope.php';
}

$auragold_sale_owner_state = '';
$auragold_working_branch_id = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $auragold_working_branch_id = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $auragold_working_branch_id = (int) $_SESSION['branch_id'];
}
if ($auragold_working_branch_id > 0 && !empty($conn)) {
    $auragold_sale_owner_state = auragold_branch_profile_state_name($conn, $auragold_working_branch_id);
}

$auragold_gst_state_name_to_id = [];
if (!empty($conn) && function_exists('getList')) {
    $gst_state_rows = @getList('SELECT id, name FROM tbl_states WHERE status = 1');
    if (is_array($gst_state_rows)) {
        foreach ($gst_state_rows as $gst_sr) {
            $gst_nm = trim((string) ($gst_sr['name'] ?? ''));
            if ($gst_nm === '') {
                continue;
            }
            $gst_key = auragold_normalize_state_label($gst_nm);
            if ($gst_key !== '') {
                $auragold_gst_state_name_to_id[$gst_key] = (int) ($gst_sr['id'] ?? 0);
            }
        }
    }
}

$auragold_tax_master_for_js = [];
if (!empty($conn) && function_exists('getList')) {
    $has_tm_scope = function_exists('auragold_tax_master_has_gst_supply_scope') && auragold_tax_master_has_gst_supply_scope($conn);
    $auragold_tm_list_sql = '';
    if (function_exists('auragold_master_list_sql_suffix')) {
        $auragold_tm_list_sql = auragold_master_list_sql_suffix($conn, 'tbl_tax_master');
    }
    if ($has_tm_scope) {
        $tm_rows = @getList("SELECT name, default_value, IFNULL(gst_supply_scope, 'local_state') AS gst_supply_scope FROM tbl_tax_master WHERE status = 1 " . $auragold_tm_list_sql . " ORDER BY sort_order ASC, id ASC");
    } else {
        $tm_rows = @getList("SELECT name, default_value FROM tbl_tax_master WHERE status = 1 " . $auragold_tm_list_sql . " ORDER BY sort_order ASC, id ASC");
    }
    if (is_array($tm_rows)) {
        foreach ($tm_rows as $trx) {
            $auragold_tax_master_for_js[] = [
                'name' => trim((string) ($trx['name'] ?? '')),
                'default_value' => (float) ($trx['default_value'] ?? 0),
                'gst_supply_scope' => $has_tm_scope ? trim((string) ($trx['gst_supply_scope'] ?? 'local_state')) : 'local_state',
            ];
        }
    }
}
