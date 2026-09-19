<?php
/**
 * Barcodes already assigned to a *different* sale person (global reservation).
 * Used to hide those lines from the Available stock pool. Optional sales_person:
 * when set, excludes rows for that person so their own assignments are not treated as "other".
 */
ob_start();
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';
require_once dirname(__DIR__) . '/includes/ensure_sales_team_inventory_assign_schema.php';

header('Content-Type: application/json; charset=utf-8');

function aits_global_bc_json_out(array $payload) {
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
    aits_global_bc_json_out(['success' => false, 'message' => 'Unauthorized', 'map' => []]);
}

auragold_ensure_sales_team_inventory_assign_schema($conn);

$sales_person = isset($_GET['sales_person']) ? trim((string) $_GET['sales_person']) : '';
$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($branch_id);
$bid_sql = $branch_id > 0 ? ' AND branch_id = ' . (int) $branch_id : '';

$map = [];
if ($sales_person === '') {
    $res = @mysqli_query($conn, 'SELECT barcode_no, sales_person FROM tbl_sales_team_inventory_assign WHERE 1=1' . $bid_sql);
} else {
    $esc = mysqli_real_escape_string($conn, $sales_person);
    $res = @mysqli_query(
        $conn,
        "SELECT barcode_no, sales_person FROM tbl_sales_team_inventory_assign WHERE sales_person <> '$esc'" . $bid_sql
    );
}
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $b = trim((string) ($row['barcode_no'] ?? ''));
        if ($b === '') {
            continue;
        }
        $map[$b] = (string) ($row['sales_person'] ?? '');
    }
    mysqli_free_result($res);
}

aits_global_bc_json_out(['success' => true, 'map' => $map]);
