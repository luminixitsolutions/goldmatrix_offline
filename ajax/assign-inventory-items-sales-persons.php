<?php
/**
 * Sales persons (active users) with assignment counts for Assign Inventory Items sidebar.
 */
ob_start();
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';
require_once dirname(__DIR__) . '/includes/user_management_schema.php';
require_once dirname(__DIR__) . '/includes/ensure_sales_team_inventory_assign_schema.php';

header('Content-Type: application/json; charset=utf-8');

function aii_sp_json_out(array $payload) {
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
    aii_sp_json_out(['success' => false, 'message' => 'Unauthorized', 'rows' => []]);
}

auragold_ensure_sales_team_inventory_assign_schema($conn);

$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($branch_id);

$counts = [];
if (@mysqli_query($conn, 'SELECT 1 FROM tbl_sales_team_inventory_assign LIMIT 1')) {
    $count_sql = 'SELECT sales_person, COUNT(*) AS c FROM tbl_sales_team_inventory_assign';
    if ($branch_id > 0) {
        $count_sql .= ' WHERE branch_id = ' . (int) $branch_id;
    }
    $count_sql .= ' GROUP BY sales_person';
    $cr = @mysqli_query($conn, $count_sql);
    if ($cr) {
        while ($row = mysqli_fetch_assoc($cr)) {
            $sp = trim((string) ($row['sales_person'] ?? ''));
            if ($sp !== '') {
                $counts[$sp] = (int) ($row['c'] ?? 0);
            }
        }
        mysqli_free_result($cr);
    }
}

$rows = [];
if ($branch_id > 0 && !empty($conn_master) && ($conn_master instanceof mysqli)) {
    auragold_ensure_user_management_columns($conn_master);
    $names = auragold_sales_person_names_for_branch_id($conn_master, $branch_id);
    foreach ($names as $disp) {
        $rows[] = [
            'sales_person'   => $disp,
            'assigned_count' => isset($counts[$disp]) ? $counts[$disp] : 0,
        ];
    }
} else {
    $users = function_exists('getList')
        ? @getList("SELECT id, Fname, Lname, Username FROM tbl_users WHERE Status = '1' ORDER BY Fname ASC, Lname ASC, Username ASC")
        : null;
    if (!is_array($users) || empty($users)) {
        $users = function_exists('getListMaster')
            ? @getListMaster("SELECT id, Fname, Lname, Username FROM tbl_users WHERE Status = '1' ORDER BY Fname ASC, Lname ASC, Username ASC")
            : null;
    }
    if (!is_array($users)) {
        $users = [];
    }
    foreach ($users as $u) {
        $fn = trim((string) ($u['Fname'] ?? ''));
        $ln = trim((string) ($u['Lname'] ?? ''));
        $disp = trim($fn . ' ' . $ln);
        if ($disp === '') {
            $disp = trim((string) ($u['Username'] ?? ''));
        }
        if ($disp === '') {
            continue;
        }
        $rows[] = [
            'sales_person'   => $disp,
            'assigned_count' => isset($counts[$disp]) ? $counts[$disp] : 0,
        ];
    }
}

$totalAssigned = array_sum($counts);

aii_sp_json_out([
    'success' => true,
    'rows' => $rows,
    'total_assigned_lines' => $totalAssigned,
]);
