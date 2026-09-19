<?php

/**
 * Rows for stock-transfer-history.php / export (same SQL as the on-screen report).
 *
 * @return array{rows: list<array<string,mixed>>, error: string}
 */
function auragold_stock_transfer_history_fetch(mysqli $conn): array {
    require_once __DIR__ . '/auragold_stock_cross_transfer_log_schema.php';
    require_once __DIR__ . '/stock_transfer_pending_schema.php';

    auragold_ensure_stock_cross_transfer_log_table($conn);
    auragold_ensure_stock_transfer_pending_table($conn);

    $hasPendingTable = false;
    $__t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_transfer_pending'");
    if ($__t && mysqli_num_rows($__t) > 0) {
        $hasPendingTable = true;
    }
    if ($__t) {
        mysqli_free_result($__t);
    }

    $refChk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
    $hasStockReferencePair = ($refChk && mysqli_num_rows($refChk) >= 2);
    if ($refChk) {
        mysqli_free_result($refChk);
    }

    $stoneCol = '';
    $diamondCol = '';
    $metalCostCol = 'ow.value AS metal_cost';
    $stoneCostCol = '0 AS stone_cost';
    $makingCostCol = '0 AS making_cost';

    $sc = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_stock');
    if ($sc) {
        $fields = [];
        while ($r = mysqli_fetch_assoc($sc)) {
            $fields[$r['Field']] = true;
        }
        mysqli_free_result($sc);
        if (!empty($fields['stone_wt'])) {
            $stoneCol = 'ow.stone_wt AS stone_wt';
        } else {
            $stoneCol = '0 AS stone_wt';
        }
        if (!empty($fields['diamond_wt'])) {
            $diamondCol = 'ow.diamond_wt AS diamond_wt';
        } else {
            $diamondCol = '0 AS diamond_wt';
        }
        if (!empty($fields['metal_value'])) {
            $metalCostCol = 'COALESCE(ow.metal_value, ow.value, 0) AS metal_cost';
        }
        if (!empty($fields['stone_amt']) || !empty($fields['stone_amount'])) {
            $f = !empty($fields['stone_amt']) ? 'ow.stone_amt' : 'ow.stone_amount';
            $stoneCostCol = 'COALESCE(' . $f . ', 0) AS stone_cost';
        }
        if (!empty($fields['making_amt']) || !empty($fields['making_amount'])) {
            $f = !empty($fields['making_amt']) ? 'ow.making_amt' : 'ow.making_amount';
            $makingCostCol = 'COALESCE(' . $f . ', 0) AS making_cost';
        }
    }

    $refWhere = '';
    if ($hasStockReferencePair) {
        $refWhere = " AND ow.reference_type = 'stock_transfer' ";
    }

    if ($hasPendingTable) {
        $sql = "
SELECT
    ow.id AS outward_id,
    ow.transaction_date,
    ow.created_at,
    ow.branch_id AS from_branch_id,
    bf.name AS from_branch_name,
    CASE
        WHEN pen.id IS NOT NULL AND pen.to_branch_id IS NOT NULL AND pen.to_branch_id > 0 THEN pen.to_branch_id
        WHEN dest.id IS NOT NULL THEN dest.branch_id
        WHEN xlog.id IS NOT NULL THEN xlog.destination_branch_id
        ELSE COALESCE(dest.branch_id, pen.to_branch_id, xlog.destination_branch_id)
    END AS to_branch_id,
    bt.name AS to_branch_name,
    ow.barcode,
    p.name AS product_name,
    COALESCE(dest.final_weight, ow.final_weight) AS gross_wt,
    COALESCE(dest.current_weight, ow.current_weight) AS net_wt,
    COALESCE(dest.current_qty, ow.current_qty) AS qty,
    $diamondCol,
    $stoneCol,
    COALESCE(dest.value, ow.value) AS purchase_value,
    $metalCostCol,
    $stoneCostCol,
    $makingCostCol,
    COALESCE(
        pen.status,
        CASE
            WHEN xlog.id IS NOT NULL AND (xlog.destination_stock_id IS NULL OR xlog.destination_stock_id = 0) THEN 'pending'
            WHEN xlog.id IS NOT NULL THEN 'received'
            ELSE ''
        END
    ) AS transfer_pending_status,
    COALESCE(pen.received_stock_id, NULLIF(xlog.destination_stock_id, 0)) AS transfer_received_stock_id,
    COALESCE(dest.id, NULLIF(xlog.destination_stock_id, 0)) AS dest_stock_id,
    '' AS against_ref
FROM tbl_stock ow
LEFT JOIN tbl_stock_transfer_pending pen ON pen.id = (
    SELECT p2.id FROM tbl_stock_transfer_pending p2
    WHERE p2.outward_stock_id = ow.id
    ORDER BY (p2.received_stock_id IS NOT NULL AND p2.received_stock_id > 0) DESC, p2.id DESC
    LIMIT 1
)
LEFT JOIN tbl_stock_cross_transfer_log xlog ON xlog.outward_stock_id = ow.id
LEFT JOIN tbl_stock dest ON dest.id = COALESCE(
    NULLIF(pen.received_stock_id, 0),
    IF(pen.id IS NULL,
        (SELECT d.id FROM tbl_stock d
         WHERE d.stock_type = 'purchase'
         AND d.product_id = ow.product_id
         AND BINARY IFNULL(d.barcode,'') = BINARY IFNULL(ow.barcode,'')
         AND DATE(d.transaction_date) = DATE(ow.transaction_date)
         AND d.branch_id <> ow.branch_id
         AND ABS(TIMESTAMPDIFF(SECOND, ow.created_at, d.created_at)) <= 45
         ORDER BY d.id ASC
         LIMIT 1),
        (SELECT d.id FROM tbl_stock d
         WHERE d.stock_type = 'purchase'
         AND pen.id IS NOT NULL
         AND (pen.received_stock_id IS NULL OR pen.received_stock_id = 0)
         AND d.product_id = ow.product_id
         AND BINARY IFNULL(d.barcode,'') = BINARY IFNULL(ow.barcode,'')
         AND d.branch_id = pen.to_branch_id
         AND d.branch_id <> ow.branch_id
         AND ABS(TIMESTAMPDIFF(SECOND, ow.created_at, d.created_at)) <= 86400
         ORDER BY d.id DESC
         LIMIT 1)
    )
)
AND (
    pen.id IS NULL
    OR pen.to_branch_id IS NULL
    OR pen.to_branch_id = 0
    OR dest.branch_id = pen.to_branch_id
)
LEFT JOIN tbl_branches bf ON ow.branch_id = bf.id
LEFT JOIN tbl_branches bt ON bt.id = CASE
    WHEN pen.id IS NOT NULL AND pen.to_branch_id IS NOT NULL AND pen.to_branch_id > 0 THEN pen.to_branch_id
    WHEN dest.id IS NOT NULL THEN dest.branch_id
    WHEN xlog.id IS NOT NULL THEN xlog.destination_branch_id
    ELSE COALESCE(dest.branch_id, pen.to_branch_id, xlog.destination_branch_id)
END
LEFT JOIN tbl_products p ON ow.product_id = p.id
WHERE ow.stock_type = 'outward'
$refWhere
AND (dest.id IS NOT NULL OR pen.id IS NOT NULL OR xlog.id IS NOT NULL)
ORDER BY ow.created_at DESC
LIMIT 5000
";
    } else {
        $sql = "
SELECT
    ow.id AS outward_id,
    ow.transaction_date,
    ow.created_at,
    ow.branch_id AS from_branch_id,
    bf.name AS from_branch_name,
    dest.branch_id AS to_branch_id,
    bt.name AS to_branch_name,
    ow.barcode,
    p.name AS product_name,
    ow.final_weight AS gross_wt,
    ow.current_weight AS net_wt,
    ow.current_qty AS qty,
    $diamondCol,
    $stoneCol,
    ow.value AS purchase_value,
    $metalCostCol,
    $stoneCostCol,
    $makingCostCol,
    '' AS transfer_pending_status,
    0 AS transfer_received_stock_id,
    0 AS dest_stock_id,
    '' AS against_ref
FROM tbl_stock ow
INNER JOIN tbl_stock dest ON dest.id = (
    SELECT d.id FROM tbl_stock d
    WHERE d.stock_type = 'purchase'
    AND d.product_id = ow.product_id
    AND BINARY IFNULL(d.barcode,'') = BINARY IFNULL(ow.barcode,'')
    AND DATE(d.transaction_date) = DATE(ow.transaction_date)
    AND d.branch_id <> ow.branch_id
    AND ABS(TIMESTAMPDIFF(SECOND, ow.created_at, d.created_at)) <= 45
    ORDER BY d.id ASC
    LIMIT 1
)
LEFT JOIN tbl_branches bf ON ow.branch_id = bf.id
LEFT JOIN tbl_branches bt ON dest.branch_id = bt.id
LEFT JOIN tbl_products p ON ow.product_id = p.id
WHERE ow.stock_type = 'outward'
$refWhere
ORDER BY ow.created_at DESC
LIMIT 5000
";
    }

    $rawRows = [];
    $sqlError = '';
    $q = @mysqli_query($conn, $sql);
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $rawRows[] = $r;
        }
        mysqli_free_result($q);
    } else {
        $sqlError = mysqli_error($conn);
    }

    // Attach transfer_doc_id / invoice_no when available (new multi-item Save).
    require_once __DIR__ . '/stock_transfer_doc_schema.php';
    if (function_exists('auragold_ensure_stock_transfer_doc_table')) {
        auragold_ensure_stock_transfer_doc_table($conn);
    }
    $hasDocTbl = false;
    $dt = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_transfer_doc'");
    if ($dt && mysqli_num_rows($dt) > 0) {
        $hasDocTbl = true;
    }
    if ($dt) {
        mysqli_free_result($dt);
    }
    $hasRef = $hasStockReferencePair;
    $hasPendDocCol = false;
    $hasLogDocCol = false;
    if ($hasPendingTable) {
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_transfer_pending LIKE 'transfer_doc_id'");
        $hasPendDocCol = ($c && mysqli_num_rows($c) > 0);
        if ($c) {
            mysqli_free_result($c);
        }
    }
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_cross_transfer_log LIKE 'transfer_doc_id'");
    $hasLogDocCol = ($c && mysqli_num_rows($c) > 0);
    if ($c) {
        mysqli_free_result($c);
    }

    foreach ($rawRows as &$rr) {
        $docId = 0;
        $owId = (int) ($rr['outward_id'] ?? 0);
        if ($hasRef && $owId > 0) {
            $dr = @mysqli_query($conn, "SELECT reference_id FROM tbl_stock WHERE id = $owId AND reference_type = 'stock_transfer' LIMIT 1");
            if ($dr && ($dx = mysqli_fetch_assoc($dr))) {
                $docId = (int) ($dx['reference_id'] ?? 0);
            }
            if ($dr) {
                mysqli_free_result($dr);
            }
        }
        if ($docId <= 0 && $hasPendDocCol && $owId > 0) {
            $dr = @mysqli_query($conn, "SELECT transfer_doc_id FROM tbl_stock_transfer_pending WHERE outward_stock_id = $owId AND transfer_doc_id IS NOT NULL AND transfer_doc_id > 0 ORDER BY id DESC LIMIT 1");
            if ($dr && ($dx = mysqli_fetch_assoc($dr))) {
                $docId = (int) ($dx['transfer_doc_id'] ?? 0);
            }
            if ($dr) {
                mysqli_free_result($dr);
            }
        }
        if ($docId <= 0 && $hasLogDocCol && $owId > 0) {
            $dr = @mysqli_query($conn, "SELECT transfer_doc_id FROM tbl_stock_cross_transfer_log WHERE outward_stock_id = $owId AND transfer_doc_id IS NOT NULL AND transfer_doc_id > 0 ORDER BY id DESC LIMIT 1");
            if ($dr && ($dx = mysqli_fetch_assoc($dr))) {
                $docId = (int) ($dx['transfer_doc_id'] ?? 0);
            }
            if ($dr) {
                mysqli_free_result($dr);
            }
        }
        $rr['transfer_doc_id'] = $docId;
        $inv = '';
        if ($docId > 0 && $hasDocTbl) {
            $ir = @mysqli_query($conn, 'SELECT invoice_no FROM tbl_stock_transfer_doc WHERE id = ' . $docId . ' LIMIT 1');
            if ($ir && ($ix = mysqli_fetch_assoc($ir))) {
                $inv = trim((string) ($ix['invoice_no'] ?? ''));
            }
            if ($ir) {
                mysqli_free_result($ir);
            }
        }
        if ($inv === '') {
            $inv = 'ST-' . str_pad((string) $owId, 6, '0', STR_PAD_LEFT);
        }
        $rr['invoice_no'] = $inv;
        $rr['group_key'] = $docId > 0 ? ('doc:' . $docId) : ('ow:' . $owId);
    }
    unset($rr);

    // Aggregate: one row per transfer document (or legacy outward id).
    $groups = [];
    foreach ($rawRows as $r) {
        $gk = (string) ($r['group_key'] ?? ('ow:' . (int) ($r['outward_id'] ?? 0)));
        if (!isset($groups[$gk])) {
            $groups[$gk] = $r;
            $groups[$gk]['line_count'] = 1;
            $groups[$gk]['net_wt'] = (float) ($r['net_wt'] ?? 0);
            $groups[$gk]['gross_wt'] = (float) ($r['gross_wt'] ?? 0);
            $groups[$gk]['qty'] = (float) ($r['qty'] ?? 0);
            $groups[$gk]['diamond_wt'] = (float) ($r['diamond_wt'] ?? 0);
            $groups[$gk]['stone_wt'] = (float) ($r['stone_wt'] ?? 0);
            $groups[$gk]['purchase_value'] = (float) ($r['purchase_value'] ?? 0);
            $groups[$gk]['metal_cost'] = (float) ($r['metal_cost'] ?? 0);
            $groups[$gk]['stone_cost'] = (float) ($r['stone_cost'] ?? 0);
            $groups[$gk]['making_cost'] = (float) ($r['making_cost'] ?? 0);
            $groups[$gk]['product_names'] = [trim((string) ($r['product_name'] ?? ''))];
            $groups[$gk]['barcodes'] = [trim((string) ($r['barcode'] ?? ''))];
            $groups[$gk]['outward_ids'] = [(int) ($r['outward_id'] ?? 0)];
            $groups[$gk]['any_in_transit'] = false;
            $pst = strtolower(trim((string) ($r['transfer_pending_status'] ?? '')));
            $rxId = (int) ($r['transfer_received_stock_id'] ?? 0);
            $destId = (int) ($r['dest_stock_id'] ?? 0);
            if ($pst === 'pending' && $rxId <= 0 && $destId <= 0) {
                $groups[$gk]['any_in_transit'] = true;
            }
            continue;
        }
        $groups[$gk]['line_count']++;
        $groups[$gk]['net_wt'] += (float) ($r['net_wt'] ?? 0);
        $groups[$gk]['gross_wt'] += (float) ($r['gross_wt'] ?? 0);
        $groups[$gk]['qty'] += (float) ($r['qty'] ?? 0);
        $groups[$gk]['diamond_wt'] += (float) ($r['diamond_wt'] ?? 0);
        $groups[$gk]['stone_wt'] += (float) ($r['stone_wt'] ?? 0);
        $groups[$gk]['purchase_value'] += (float) ($r['purchase_value'] ?? 0);
        $groups[$gk]['metal_cost'] += (float) ($r['metal_cost'] ?? 0);
        $groups[$gk]['stone_cost'] += (float) ($r['stone_cost'] ?? 0);
        $groups[$gk]['making_cost'] += (float) ($r['making_cost'] ?? 0);
        $pn = trim((string) ($r['product_name'] ?? ''));
        if ($pn !== '' && !in_array($pn, $groups[$gk]['product_names'], true)) {
            $groups[$gk]['product_names'][] = $pn;
        }
        $bc = trim((string) ($r['barcode'] ?? ''));
        if ($bc !== '' && !in_array($bc, $groups[$gk]['barcodes'], true)) {
            $groups[$gk]['barcodes'][] = $bc;
        }
        $groups[$gk]['outward_ids'][] = (int) ($r['outward_id'] ?? 0);
        $pst = strtolower(trim((string) ($r['transfer_pending_status'] ?? '')));
        $rxId = (int) ($r['transfer_received_stock_id'] ?? 0);
        $destId = (int) ($r['dest_stock_id'] ?? 0);
        if ($pst === 'pending' && $rxId <= 0 && $destId <= 0) {
            $groups[$gk]['any_in_transit'] = true;
        }
    }

    $rows = [];
    foreach ($groups as $g) {
        $names = array_values(array_filter($g['product_names'] ?? []));
        $n = (int) ($g['line_count'] ?? 1);
        if ($n > 1) {
            $g['product_name'] = ($names[0] ?? 'Items') . ' +' . ($n - 1) . ' more';
            $bcs = array_values(array_filter($g['barcodes'] ?? []));
            $g['barcode'] = count($bcs) === 1 ? $bcs[0] : ($n . ' items');
        } else {
            $g['product_name'] = $names[0] ?? ($g['product_name'] ?? '');
        }
        if (!empty($g['any_in_transit'])) {
            $g['transfer_pending_status'] = 'pending';
            $g['transfer_received_stock_id'] = 0;
            $g['dest_stock_id'] = 0;
        } else {
            $g['transfer_pending_status'] = 'received';
        }
        $rows[] = $g;
    }

    return ['rows' => $rows, 'error' => $sqlError, 'line_rows' => $rawRows];
}

/**
 * Line items for one transfer invoice / document (modal detail).
 *
 * @return list<array<string,mixed>>
 */
function auragold_stock_transfer_history_lines_for_doc(mysqli $conn, int $docId = 0, int $outwardId = 0, string $invoiceNo = ''): array {
    $pack = auragold_stock_transfer_history_fetch($conn);
    $lines = $pack['line_rows'] ?? [];
    $invoiceNo = trim($invoiceNo);
    $out = [];
    foreach ($lines as $r) {
        $d = (int) ($r['transfer_doc_id'] ?? 0);
        $o = (int) ($r['outward_id'] ?? 0);
        $inv = trim((string) ($r['invoice_no'] ?? ''));
        if ($docId > 0 && $d === $docId) {
            $out[] = $r;
        } elseif ($docId <= 0 && $outwardId > 0 && $o === $outwardId) {
            $out[] = $r;
        } elseif ($docId <= 0 && $outwardId <= 0 && $invoiceNo !== '' && strcasecmp($inv, $invoiceNo) === 0) {
            $out[] = $r;
        }
    }
    // If doc_id was set but no lines matched (stale id), fall back to invoice_no / outward_id.
    if ($out === [] && $docId > 0) {
        foreach ($lines as $r) {
            $o = (int) ($r['outward_id'] ?? 0);
            $inv = trim((string) ($r['invoice_no'] ?? ''));
            if ($invoiceNo !== '' && strcasecmp($inv, $invoiceNo) === 0) {
                $out[] = $r;
            } elseif ($outwardId > 0 && $o === $outwardId) {
                $out[] = $r;
            }
        }
    }
    return $out;
}

/**
 * Human-readable status for export columns.
 */
function auragold_stock_transfer_history_status_label(array $row): string {
    $pst = strtolower(trim((string) ($row['transfer_pending_status'] ?? '')));
    $rxId = (int) ($row['transfer_received_stock_id'] ?? 0);
    $destId = (int) ($row['dest_stock_id'] ?? 0);
    $inTransit = ($pst === 'pending' && $rxId <= 0 && $destId <= 0);
    return $inTransit ? 'In transit' : 'Received';
}
