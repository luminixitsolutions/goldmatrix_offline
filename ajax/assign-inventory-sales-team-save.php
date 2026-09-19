<?php
/**
 * Save assigned inventory rows for the selected sales person (replaces existing rows for that person).
 */
ob_start();
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/auragold_branch_data_scope.php';
require_once dirname(__DIR__) . '/includes/ensure_sales_team_inventory_assign_schema.php';

header('Content-Type: application/json; charset=utf-8');

function aits_assign_json_out(array $payload) {
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
    aits_assign_json_out(['success' => false, 'message' => 'Unauthorized']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    aits_assign_json_out(['success' => false, 'message' => 'Use POST']);
}

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) {
    aits_assign_json_out(['success' => false, 'message' => 'Invalid JSON']);
}

$sales_person = trim((string) ($in['sales_person'] ?? ''));
if ($sales_person === '') {
    aits_assign_json_out(['success' => false, 'message' => 'Select a sale person']);
}

$branch_id = isset($in['branch_id']) ? (int) $in['branch_id'] : 0;
$branch_id = auragold_resolve_branch_id_for_session($branch_id);
if ($branch_id <= 0) {
    aits_assign_json_out(['success' => false, 'message' => 'Select a branch']);
}

auragold_ensure_sales_team_inventory_assign_schema($conn);

/**
 * Barcode must be "available" for this branch using the same grouped balance rules as
 * ajax/rfid-available-stock.php (Stock Journal merge can zero current_* while opening_* still counts).
 */
function aits_barcode_available_in_branch(mysqli $conn, $barcode, $branch_id) {
    $bc = mysqli_real_escape_string($conn, (string) $barcode);
    $bid = (int) $branch_id;
    if ($bid <= 0 || $bc === '') {
        return false;
    }

    $stock_has_reference_type = false;
    $ref_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_type'");
    if ($ref_chk && mysqli_num_rows($ref_chk) > 0) {
        $stock_has_reference_type = true;
    }
    if ($ref_chk) {
        mysqli_free_result($ref_chk);
    }

    $sold_bc_join = '';
    if ($stock_has_reference_type) {
        $sold_bc_join = "
    LEFT JOIN (
        SELECT barcode, branch_id
        FROM tbl_stock
        WHERE status = 1
          AND (stock_type IS NULL OR LOWER(TRIM(stock_type)) = 'outward')
          AND (LOWER(TRIM(COALESCE(reference_type, ''))) = 'sale_invoice')
          AND (barcode IS NOT NULL AND TRIM(COALESCE(barcode, '')) <> '')
        GROUP BY barcode, branch_id
    ) sold_bc ON sold_bc.barcode = s.barcode AND sold_bc.branch_id = s.branch_id";
    }

    if ($stock_has_reference_type) {
        $having_balance = 'HAVING (
        (SUM(COALESCE(s.current_qty,0)) > 0 OR SUM(COALESCE(s.current_weight,0)) > 0)
        OR (
            MAX(CASE WHEN sold_bc.barcode IS NOT NULL THEN 1 ELSE 0 END) = 0
            AND SUM(CASE
                WHEN LOWER(TRIM(COALESCE(s.stock_type, \'\'))) IN (\'purchase\', \'opening\', \'inward\', \'balance\')
                 AND (
                    LOWER(TRIM(COALESCE(s.reference_type, \'\'))) = \'stock_journal\'
                    OR (
                        TRIM(COALESCE(s.reference_type, \'\')) = \'\'
                        AND COALESCE(s.opening_weight, s.final_weight, 0) > 0
                    )
                 )
                THEN COALESCE(s.opening_weight, s.final_weight, 0)
                ELSE 0
            END) > 0
        )
    )';
    } else {
        $having_balance = 'HAVING (
        (SUM(COALESCE(s.current_qty,0)) > 0 OR SUM(COALESCE(s.current_weight,0)) > 0)
        OR SUM(CASE WHEN LOWER(TRIM(COALESCE(s.stock_type, \'\'))) IN (\'purchase\', \'opening\', \'inward\', \'balance\')
            THEN COALESCE(s.opening_weight, s.final_weight, 0) ELSE 0 END) > 0
        OR SUM(CASE WHEN LOWER(TRIM(COALESCE(s.stock_type, \'\'))) IN (\'purchase\', \'opening\', \'inward\', \'balance\')
            THEN COALESCE(s.opening_qty, 0) ELSE 0 END) > 0
    )';
    }

    $sql = 'SELECT 1 AS ok FROM (
        SELECT s.barcode, s.branch_id
        FROM tbl_stock s
        LEFT JOIN tbl_products p ON s.product_id = p.id
        LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
        ' . $sold_bc_join . '
        WHERE s.status = 1
          AND s.barcode = \'' . $bc . '\'
          AND s.branch_id = ' . $bid . '
          AND (s.stock_type IS NULL OR LOWER(TRIM(s.stock_type)) <> \'outward\')
          AND (s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,\'\')) <> \'\')
        GROUP BY s.barcode, s.branch_id
        ' . $having_balance . '
    ) t LIMIT 1';

    $r = @mysqli_query($conn, $sql);
    if (!$r) {
        return false;
    }
    $ok = mysqli_num_rows($r) > 0;
    mysqli_free_result($r);
    return $ok;
}

$rows = $in['rows'] ?? [];
if (!is_array($rows)) {
    $rows = [];
}
if (count($rows) > 5000) {
    aits_assign_json_out(['success' => false, 'message' => 'Too many rows (max 5000)']);
}

$sp_esc = mysqli_real_escape_string($conn, $sales_person);

$bcList = [];
foreach ($rows as $r) {
    if (!is_array($r)) {
        continue;
    }
    $bc = trim((string) ($r['barcode_no'] ?? ''));
    if ($bc !== '') {
        $bcList[] = $bc;
    }
}
$bcList = array_unique($bcList);

foreach ($bcList as $bc) {
    $bc_esc = mysqli_real_escape_string($conn, $bc);
    $conflict = getRecord("SELECT sales_person FROM tbl_sales_team_inventory_assign WHERE barcode_no = '$bc_esc' AND sales_person <> '$sp_esc' LIMIT 1");
    if ($conflict && isset($conflict['sales_person']) && trim((string) $conflict['sales_person']) !== '') {
        aits_assign_json_out([
            'success' => false,
            'message' => 'Barcode ' . $bc . ' is already assigned to ' . trim((string) $conflict['sales_person']) . '.',
        ]);
    }
    if (!aits_barcode_available_in_branch($conn, $bc, $branch_id)) {
        aits_assign_json_out([
            'success' => false,
            'message' => 'Barcode ' . $bc . ' is not available in stock for the selected branch.',
        ]);
    }
}

mysqli_begin_transaction($conn);
$bid = (int) $branch_id;
if (!mysqli_query($conn, "DELETE FROM tbl_sales_team_inventory_assign WHERE sales_person = '$sp_esc' AND branch_id = $bid")) {
    mysqli_rollback($conn);
    aits_assign_json_out(['success' => false, 'message' => 'Could not clear previous assignments: ' . mysqli_error($conn)]);
}

$inserted = 0;
foreach ($rows as $r) {
    if (!is_array($r)) {
        continue;
    }
    $bc = trim((string) ($r['barcode_no'] ?? ''));
    if ($bc === '') {
        continue;
    }
    $bc_esc = mysqli_real_escape_string($conn, $bc);
    $json = json_encode($r, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        continue;
    }
    $json_esc = mysqli_real_escape_string($conn, $json);
    $by = $uid > 0 ? (string) (int) $uid : 'NULL';
    $sql = "INSERT INTO tbl_sales_team_inventory_assign (sales_person, branch_id, barcode_no, row_json, created_by) VALUES ('$sp_esc', $bid, '$bc_esc', '$json_esc', $by)";
    if (!mysqli_query($conn, $sql)) {
        $err = mysqli_error($conn);
        mysqli_rollback($conn);
        if (stripos($err, 'Duplicate') !== false || stripos($err, 'UNIQUE') !== false) {
            aits_assign_json_out([
                'success' => false,
                'message' => 'Barcode ' . $bc . ' is already assigned to another sale person.',
            ]);
        }
        aits_assign_json_out(['success' => false, 'message' => 'Save failed: ' . $err]);
    }
    $inserted++;
}

mysqli_commit($conn);

aits_assign_json_out([
    'success' => true,
    'message' => 'Saved',
    'saved_count' => $inserted,
    'sales_person' => $sales_person,
    'branch_id'    => $branch_id,
]);
