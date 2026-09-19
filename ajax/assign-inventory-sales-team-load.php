<?php
/**
 * Load saved assigned inventory rows for a sales person.
 */
ob_start();
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';
require_once dirname(__DIR__) . '/includes/ensure_sales_team_inventory_assign_schema.php';

header('Content-Type: application/json; charset=utf-8');

function aits_load_json_out(array $payload) {
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
    aits_load_json_out(['success' => false, 'message' => 'Unauthorized', 'rows' => []]);
}

$sales_person = isset($_GET['sales_person']) ? trim((string) $_GET['sales_person']) : '';
if ($sales_person === '') {
    aits_load_json_out(['success' => true, 'rows' => []]);
}

$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($branch_id);
if ($branch_id <= 0) {
    aits_load_json_out(['success' => true, 'rows' => [], 'message' => 'Select a branch']);
}

auragold_ensure_sales_team_inventory_assign_schema($conn);

$sp_esc = mysqli_real_escape_string($conn, $sales_person);
$bid = (int) $branch_id;
$res = @mysqli_query(
    $conn,
    "SELECT barcode_no, row_json FROM tbl_sales_team_inventory_assign WHERE sales_person = '$sp_esc' AND branch_id = $bid ORDER BY id ASC"
);
$out = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $j = null;
        if (!empty($row['row_json'])) {
            $j = json_decode((string) $row['row_json'], true);
        }
        if (is_array($j)) {
            $out[] = $j;
        } else {
            $out[] = [
                'barcode_no' => (string) ($row['barcode_no'] ?? ''),
                'rfid_code' => '',
                'product_name' => '',
                'imageUrls' => '',
                'amount' => '',
                'description' => '',
                'design_no' => '',
                'gross_wt' => '',
                'final_wt' => '',
                'invoice_no' => '',
                'metal_value' => '',
                'net_amount' => '',
                'net_amount_with_tax' => '',
                'quantity' => 1,
                'tax_amount' => '',
                'active' => 'Yes',
            ];
        }
    }
    mysqli_free_result($res);
}

aits_load_json_out(['success' => true, 'rows' => $out]);
