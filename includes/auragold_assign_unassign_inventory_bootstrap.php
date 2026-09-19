<?php
/**
 * Shared bootstrap for Assign Inventory / UnAssign Inventory pages.
 * Expects $ai_page_mode = 'assign' | 'unassign' before include.
 */
if (!isset($ai_page_mode) || !in_array($ai_page_mode, ['assign', 'unassign'], true)) {
    $ai_page_mode = 'assign';
}

require_once __DIR__ . '/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once __DIR__ . '/auragold_branch_data_scope.php';
require_once __DIR__ . '/user_management_schema.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$ai_is_assign = ($ai_page_mode === 'assign');
$ai_page_title = $ai_is_assign ? 'Assign Inventory' : 'UnAssign Inventory';
$ai_page_lead = $ai_is_assign
    ? 'Select a sale person, choose available stock barcodes, then Assign.'
    : 'Select assigned inventory and UnAssign it from the sale person.';

$ai_branches = [];
if (function_exists('getListMaster')) {
    $ai_branches = @getListMaster('SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC');
}
if (!is_array($ai_branches)) {
    $ai_branches = [];
}

$ai_default_branch_id = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $ai_default_branch_id = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $ai_default_branch_id = (int) $_SESSION['branch_id'];
} elseif (function_exists('auragold_effective_branch_id')) {
    $ai_default_branch_id = (int) auragold_effective_branch_id();
}

if ($ai_default_branch_id > 0 && !empty($conn_master) && function_exists('getRecordMaster')) {
    $has = false;
    foreach ($ai_branches as $ab) {
        if ((int) ($ab['id'] ?? 0) === $ai_default_branch_id) {
            $has = true;
            break;
        }
    }
    if (!$has) {
        $brx = getRecordMaster('SELECT id, name FROM tbl_branches WHERE id = ' . (int) $ai_default_branch_id . ' LIMIT 1');
        if ($brx && !empty($brx['id'])) {
            $ai_branches[] = [
                'id' => (int) $brx['id'],
                'name' => trim((string) ($brx['name'] ?? ('Branch #' . (int) $brx['id']))),
            ];
        }
    }
}

$ai_branch_locked = ($ai_default_branch_id > 0);
$ai_branch_display_name = '';
if ($ai_branch_locked) {
    foreach ($ai_branches as $ab) {
        if ((int) ($ab['id'] ?? 0) === $ai_default_branch_id) {
            $ai_branch_display_name = trim((string) ($ab['name'] ?? ''));
            break;
        }
    }
    if ($ai_branch_display_name === '' && !empty($conn_master) && function_exists('getRecordMaster')) {
        $rn = getRecordMaster('SELECT name FROM tbl_branches WHERE id = ' . (int) $ai_default_branch_id . ' LIMIT 1');
        if ($rn) {
            $ai_branch_display_name = trim((string) ($rn['name'] ?? ''));
        }
    }
    if ($ai_branch_display_name === '') {
        $ai_branch_display_name = 'Branch #' . (int) $ai_default_branch_id;
    }
}

$ai_sales_persons = [];
if (!empty($conn_master) && $ai_default_branch_id > 0) {
    auragold_ensure_user_management_columns($conn_master);
    $ai_sales_persons = auragold_sales_person_names_for_branch_id($conn_master, $ai_default_branch_id);
}
if (!is_array($ai_sales_persons)) {
    $ai_sales_persons = [];
}
