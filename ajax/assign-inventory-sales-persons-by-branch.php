<?php
/**
 * JSON: sales person display names for tbl_users assigned to a branch (user_branch_ids).
 */
ob_start();
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';
require_once dirname(__DIR__) . '/includes/user_management_schema.php';

header('Content-Type: application/json; charset=utf-8');

function aits_spb_json_out(array $payload)
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    echo json_encode($payload, $flags);
    exit;
}

$uid = (int) ($_SESSION['user_id'] ?? 0);
if ($uid <= 0) {
    aits_spb_json_out(['success' => false, 'message' => 'Unauthorized', 'names' => []]);
}

$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($branch_id);
if ($branch_id <= 0) {
    aits_spb_json_out(['success' => true, 'names' => []]);
}

if (empty($conn_master) || !($conn_master instanceof mysqli)) {
    aits_spb_json_out(['success' => false, 'message' => 'Database unavailable', 'names' => []]);
}

auragold_ensure_user_management_columns($conn_master);
$names = auragold_sales_person_names_for_branch_id($conn_master, $branch_id);

aits_spb_json_out(['success' => true, 'names' => $names]);
