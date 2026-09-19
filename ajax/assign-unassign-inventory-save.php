<?php
/**
 * Incremental assign / unassign barcodes for sales-team inventory.
 * POST JSON: { action: 'assign'|'unassign', sales_person, branch_id, rows: [...] }
 * - assign: merge rows into that person's current assignments
 * - unassign: remove listed barcodes (from that person, or globally if sales_person empty)
 */
ob_start();
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';
require_once dirname(__DIR__) . '/includes/ensure_sales_team_inventory_assign_schema.php';

header('Content-Type: application/json; charset=utf-8');

function aiu_json_out(array $payload) {
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
    aiu_json_out(['success' => false, 'message' => 'Unauthorized']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    aiu_json_out(['success' => false, 'message' => 'Use POST']);
}

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) {
    aiu_json_out(['success' => false, 'message' => 'Invalid JSON']);
}

$action = strtolower(trim((string) ($in['action'] ?? '')));
if (!in_array($action, ['assign', 'unassign'], true)) {
    aiu_json_out(['success' => false, 'message' => 'Invalid action']);
}

$sales_person = trim((string) ($in['sales_person'] ?? ''));
$branch_id = isset($in['branch_id']) ? (int) $in['branch_id'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($branch_id);
if ($branch_id <= 0) {
    aiu_json_out(['success' => false, 'message' => 'Select a branch']);
}

$rows_in = isset($in['rows']) && is_array($in['rows']) ? $in['rows'] : [];
$barcodes = [];
$row_by_bc = [];
foreach ($rows_in as $r) {
    if (!is_array($r)) {
        continue;
    }
    $bc = trim((string) ($r['barcode_no'] ?? ''));
    if ($bc === '') {
        continue;
    }
    $barcodes[] = $bc;
    $row_by_bc[$bc] = $r;
}
$barcodes = array_values(array_unique($barcodes));
if ($barcodes === []) {
    aiu_json_out(['success' => false, 'message' => 'Select at least one row']);
}

auragold_ensure_sales_team_inventory_assign_schema($conn);
$bid = (int) $branch_id;
$by = $uid > 0 ? (string) (int) $uid : 'NULL';

if ($action === 'unassign') {
    $esc_list = [];
    foreach ($barcodes as $bc) {
        $esc_list[] = "'" . mysqli_real_escape_string($conn, $bc) . "'";
    }
    $in_sql = implode(',', $esc_list);
    $where = "barcode_no IN ($in_sql) AND branch_id = $bid";
    if ($sales_person !== '') {
        $sp_esc = mysqli_real_escape_string($conn, $sales_person);
        $where .= " AND sales_person = '$sp_esc'";
    }
    if (!mysqli_query($conn, "DELETE FROM tbl_sales_team_inventory_assign WHERE $where")) {
        aiu_json_out(['success' => false, 'message' => 'Unassign failed: ' . mysqli_error($conn)]);
    }
    aiu_json_out([
        'success' => true,
        'message' => 'Unassigned ' . count($barcodes) . ' item(s).',
        'count' => count($barcodes),
    ]);
}

// assign
if ($sales_person === '') {
    aiu_json_out(['success' => false, 'message' => 'Select a sale person']);
}
$sp_esc = mysqli_real_escape_string($conn, $sales_person);

foreach ($barcodes as $bc) {
    $bc_esc = mysqli_real_escape_string($conn, $bc);
    $conflict = getRecord(
        "SELECT sales_person FROM tbl_sales_team_inventory_assign
         WHERE barcode_no = '$bc_esc' AND sales_person <> '$sp_esc' LIMIT 1"
    );
    if ($conflict && trim((string) ($conflict['sales_person'] ?? '')) !== '') {
        aiu_json_out([
            'success' => false,
            'message' => 'Barcode ' . $bc . ' is already assigned to ' . trim((string) $conflict['sales_person']) . '.',
        ]);
    }
}

mysqli_begin_transaction($conn);
$inserted = 0;
foreach ($barcodes as $bc) {
    $bc_esc = mysqli_real_escape_string($conn, $bc);
    $exists = getRecord(
        "SELECT id FROM tbl_sales_team_inventory_assign
         WHERE barcode_no = '$bc_esc' AND sales_person = '$sp_esc' AND branch_id = $bid LIMIT 1"
    );
    if ($exists) {
        continue;
    }
    $payload = $row_by_bc[$bc];
    if (!is_array($payload)) {
        $payload = ['barcode_no' => $bc];
    }
    $payload['barcode_no'] = $bc;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        continue;
    }
    $json_esc = mysqli_real_escape_string($conn, $json);
    $sql = "INSERT INTO tbl_sales_team_inventory_assign (sales_person, branch_id, barcode_no, row_json, created_by)
            VALUES ('$sp_esc', $bid, '$bc_esc', '$json_esc', $by)";
    if (!mysqli_query($conn, $sql)) {
        $err = mysqli_error($conn);
        mysqli_rollback($conn);
        aiu_json_out(['success' => false, 'message' => 'Assign failed: ' . $err]);
    }
    $inserted++;
}
mysqli_commit($conn);

aiu_json_out([
    'success' => true,
    'message' => $inserted > 0
        ? ('Assigned ' . $inserted . ' item(s) to ' . $sales_person . '.')
        : 'Selected items were already assigned.',
    'count' => $inserted,
    'sales_person' => $sales_person,
]);
