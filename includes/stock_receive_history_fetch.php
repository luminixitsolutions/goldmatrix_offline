<?php

require_once __DIR__ . '/stock_transfer_pending_schema.php';

/**
 * Stock receive history: pending staging rows + received lines (same queries as stock-receive-history.php).
 *
 * @return array{pending: array<int, array<string, mixed>>, received: array<int, array<string, mixed>>, error: string}
 */
function auragold_stock_receive_history_fetch(mysqli $stXferConn): array
{
    $pendingRows = [];
    $receivedRows = [];
    $sqlError = '';

    $refChk = @mysqli_query($stXferConn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_type'");
    $hasRefType = ($refChk && mysqli_num_rows($refChk) > 0);
    if ($refChk) {
        mysqli_free_result($refChk);
    }

    $stoneColRecv = '';
    $diamondColRecv = '';
    $metalCostColRecv = 'recv.value AS metal_cost';
    $stoneCostColRecv = '0 AS stone_cost';
    $makingCostColRecv = '0 AS making_cost';

    $stoneColOw = '';
    $diamondColOw = '';

    $fields = [];
    $sc = @mysqli_query($stXferConn, 'SHOW COLUMNS FROM tbl_stock');
    if ($sc) {
        while ($r = mysqli_fetch_assoc($sc)) {
            $fields[$r['Field']] = true;
        }
        mysqli_free_result($sc);
        if (!empty($fields['stone_wt'])) {
            $stoneColRecv = 'recv.stone_wt AS stone_wt';
            $stoneColOw = 'COALESCE(ow.stone_wt, 0) AS stone_wt';
        } else {
            $stoneColRecv = '0 AS stone_wt';
            $stoneColOw = '0 AS stone_wt';
        }
        if (!empty($fields['diamond_wt'])) {
            $diamondColRecv = 'recv.diamond_wt AS diamond_wt';
            $diamondColOw = 'COALESCE(ow.diamond_wt, 0) AS diamond_wt';
        } else {
            $diamondColRecv = '0 AS diamond_wt';
            $diamondColOw = '0 AS diamond_wt';
        }
        if (!empty($fields['metal_value'])) {
            $metalCostColRecv = 'COALESCE(recv.metal_value, recv.value, 0) AS metal_cost';
        }
        if (!empty($fields['stone_amt']) || !empty($fields['stone_amount'])) {
            $f = !empty($fields['stone_amt']) ? 'recv.stone_amt' : 'recv.stone_amount';
            $stoneCostColRecv = 'COALESCE(' . $f . ', 0) AS stone_cost';
        }
        if (!empty($fields['making_amt']) || !empty($fields['making_amount'])) {
            $f = !empty($fields['making_amt']) ? 'recv.making_amt' : 'recv.making_amount';
            $makingCostColRecv = 'COALESCE(' . $f . ', 0) AS making_cost';
        }
    }

    $metalCostColPend = 'COALESCE(ow.value, pen.value, 0) AS metal_cost';
    if (!empty($fields['metal_value'])) {
        $metalCostColPend = 'COALESCE(ow.metal_value, ow.value, pen.value, 0) AS metal_cost';
    }

    $stoneCostColPend = '0 AS stone_cost';
    $makingCostColPend = '0 AS making_cost';
    if (!empty($fields['stone_amt']) || !empty($fields['stone_amount'])) {
        $f = !empty($fields['stone_amt']) ? 'ow.stone_amt' : 'ow.stone_amount';
        $stoneCostColPend = 'COALESCE(' . $f . ', 0) AS stone_cost';
    }
    if (!empty($fields['making_amt']) || !empty($fields['making_amount'])) {
        $f = !empty($fields['making_amt']) ? 'ow.making_amt' : 'ow.making_amount';
        $makingCostColPend = 'COALESCE(' . $f . ', 0) AS making_cost';
    }

    $refWhereRecv = '';
    if ($hasRefType) {
        $refWhereRecv = " AND recv.reference_type = 'stock_transfer' ";
    }

    $wb = 0;
    if (!empty($_SESSION['working_branch_id'])) {
        $wb = (int) $_SESSION['working_branch_id'];
    } elseif (!empty($_SESSION['branch_id'])) {
        $wb = (int) $_SESSION['branch_id'];
    }
    $branchWhereRecv = '';
    $branchWherePend = '';
    if ($wb > 0) {
        $branchWhereRecv = ' AND recv.branch_id = ' . $wb . ' ';
        $branchWherePend = ' AND pen.to_branch_id = ' . $wb . ' ';
    }

    if (!auragold_ensure_stock_transfer_pending_table($stXferConn)) {
        $sqlError = 'Could not ensure tbl_stock_transfer_pending: ' . mysqli_error($stXferConn);
        return ['pending' => [], 'received' => [], 'error' => $sqlError];
    }

    $sqlPend = "
SELECT
    pen.id AS pending_id,
    pen.transfer_date,
    pen.created_at,
    pen.to_branch_id,
    bt.name AS to_branch_name,
    pen.from_branch_id,
    bf.name AS from_branch_name,
    pen.barcode,
    p.name AS product_name,
    pen.move_wt AS gross_wt,
    pen.move_wt AS net_wt,
    pen.move_qty AS qty,
    $diamondColOw,
    $stoneColOw,
    COALESCE(pen.value, 0) AS purchase_value,
    $metalCostColPend,
    $stoneCostColPend,
    $makingCostColPend,
    '' AS against_ref
FROM tbl_stock_transfer_pending pen
LEFT JOIN tbl_stock ow ON ow.id = pen.outward_stock_id
LEFT JOIN tbl_branches bt ON pen.to_branch_id = bt.id
LEFT JOIN tbl_branches bf ON pen.from_branch_id = bf.id
LEFT JOIN tbl_products p ON pen.product_id = p.id
WHERE pen.status = 'pending'
$branchWherePend
ORDER BY pen.created_at DESC
LIMIT 5000
";
    $q1 = @mysqli_query($stXferConn, $sqlPend);
    if ($q1) {
        while ($r = mysqli_fetch_assoc($q1)) {
            $pendingRows[] = $r;
        }
        mysqli_free_result($q1);
    } else {
        $sqlError = mysqli_error($stXferConn);
    }

    $sqlReceivedNew = "
SELECT
    recv.id AS receive_stock_id,
    pen.id AS pending_id,
    pen.received_at,
    recv.transaction_date,
    recv.created_at,
    recv.branch_id AS to_branch_id,
    bt.name AS to_branch_name,
    pen.from_branch_id AS from_branch_id,
    bf.name AS from_branch_name,
    recv.barcode,
    p.name AS product_name,
    recv.final_weight AS gross_wt,
    recv.current_weight AS net_wt,
    recv.current_qty AS qty,
    $diamondColRecv,
    $stoneColRecv,
    recv.value AS purchase_value,
    $metalCostColRecv,
    $stoneCostColRecv,
    $makingCostColRecv,
    'received_pending' AS receive_source,
    '' AS against_ref
FROM tbl_stock_transfer_pending pen
INNER JOIN tbl_stock recv ON recv.id = pen.received_stock_id
LEFT JOIN tbl_stock ow ON ow.id = pen.outward_stock_id
LEFT JOIN tbl_branches bt ON recv.branch_id = bt.id
LEFT JOIN tbl_branches bf ON bf.id = pen.from_branch_id
LEFT JOIN tbl_products p ON recv.product_id = p.id
WHERE pen.status = 'received'
$branchWhereRecv
ORDER BY pen.received_at DESC
LIMIT 5000
";
    $q2 = @mysqli_query($stXferConn, $sqlReceivedNew);
    if ($q2) {
        while ($r = mysqli_fetch_assoc($q2)) {
            $receivedRows[] = $r;
        }
        mysqli_free_result($q2);
    } elseif ($sqlError === '') {
        $sqlError = mysqli_error($stXferConn);
    }

    if ($hasRefType && $sqlError === '') {
        $legacyBranch = $wb > 0 ? ' AND recv.branch_id = ' . $wb . ' ' : '';
        $sqlLegacy = "
SELECT
    recv.id AS receive_stock_id,
    NULL AS pending_id,
    recv.transaction_date,
    recv.created_at,
    recv.branch_id AS to_branch_id,
    bt.name AS to_branch_name,
    ow.branch_id AS from_branch_id,
    bf.name AS from_branch_name,
    recv.barcode,
    p.name AS product_name,
    recv.final_weight AS gross_wt,
    recv.current_weight AS net_wt,
    recv.current_qty AS qty,
    $diamondColRecv,
    $stoneColRecv,
    recv.value AS purchase_value,
    $metalCostColRecv,
    $stoneCostColRecv,
    $makingCostColRecv,
    'legacy' AS receive_source,
    '' AS against_ref
FROM tbl_stock recv
INNER JOIN tbl_stock ow ON ow.id = (
    SELECT o.id FROM tbl_stock o
    WHERE o.stock_type = 'outward'
    AND o.product_id = recv.product_id
    AND BINARY IFNULL(o.barcode,'') = BINARY IFNULL(recv.barcode,'')
    AND DATE(o.transaction_date) = DATE(recv.transaction_date)
    AND o.branch_id <> recv.branch_id
    AND ABS(TIMESTAMPDIFF(SECOND, o.created_at, recv.created_at)) <= 45
    ORDER BY o.id ASC
    LIMIT 1
)
LEFT JOIN tbl_branches bt ON recv.branch_id = bt.id
LEFT JOIN tbl_branches bf ON ow.branch_id = bf.id
LEFT JOIN tbl_products p ON recv.product_id = p.id
WHERE recv.stock_type = 'purchase'
$refWhereRecv
$legacyBranch
AND NOT EXISTS (
    SELECT 1 FROM tbl_stock_transfer_pending p2 WHERE p2.received_stock_id = recv.id
)
ORDER BY recv.created_at DESC
LIMIT 2000
";
        $q3 = @mysqli_query($stXferConn, $sqlLegacy);
        if ($q3) {
            while ($r = mysqli_fetch_assoc($q3)) {
                $receivedRows[] = $r;
            }
            mysqli_free_result($q3);
        }

        if ($wb > 0) {
            $sqlCrossDb = "
SELECT
    recv.id AS receive_stock_id,
    NULL AS pending_id,
    recv.created_at AS received_at,
    recv.transaction_date,
    recv.created_at,
    recv.branch_id AS to_branch_id,
    bt.name AS to_branch_name,
    NULL AS from_branch_id,
    '' AS from_branch_name,
    recv.barcode,
    p.name AS product_name,
    recv.final_weight AS gross_wt,
    recv.current_weight AS net_wt,
    recv.current_qty AS qty,
    $diamondColRecv,
    $stoneColRecv,
    recv.value AS purchase_value,
    $metalCostColRecv,
    $stoneCostColRecv,
    $makingCostColRecv,
    'cross_db' AS receive_source,
    '' AS against_ref
FROM tbl_stock recv
LEFT JOIN tbl_branches bt ON recv.branch_id = bt.id
LEFT JOIN tbl_products p ON recv.product_id = p.id
WHERE recv.stock_type = 'purchase'
AND recv.reference_type = 'stock_transfer'
AND recv.branch_id = $wb
AND NOT EXISTS (
    SELECT 1 FROM tbl_stock_transfer_pending p2 WHERE p2.received_stock_id = recv.id
)
ORDER BY recv.created_at DESC
LIMIT 2000
";
            $q4 = @mysqli_query($stXferConn, $sqlCrossDb);
            if ($q4) {
                while ($r = mysqli_fetch_assoc($q4)) {
                    $receivedRows[] = $r;
                }
                mysqli_free_result($q4);
            }
        }
    }

    usort($receivedRows, static function ($a, $b) {
        $ta = strtotime($a['received_at'] ?? $a['created_at'] ?? '1970-01-01');
        $tb = strtotime($b['received_at'] ?? $b['created_at'] ?? '1970-01-01');
        return $tb <=> $ta;
    });

    return ['pending' => $pendingRows, 'received' => $receivedRows, 'error' => $sqlError];
}

function auragold_stock_receive_received_status_label(array $row): string
{
    $src = (string) ($row['receive_source'] ?? '');
    if ($src === 'legacy') {
        return 'Legacy';
    }
    if ($src === 'cross_db') {
        return 'Cross-DB';
    }
    return 'In stock';
}
