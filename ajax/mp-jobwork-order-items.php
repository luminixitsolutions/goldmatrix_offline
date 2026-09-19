<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_mfg_jobwork_queue_line_weights.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid id']);
    exit;
}

$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    echo json_encode(['ok' => true, 'items' => []]);
    exit;
}
mysqli_free_result($chk);

$ji_cols = [];
$icq = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
if ($icq) {
    while ($c = mysqli_fetch_assoc($icq)) {
        $fn = (string) ($c['Field'] ?? '');
        if ($fn !== '') {
            $ji_cols[$fn] = true;
        }
    }
    mysqli_free_result($icq);
}

$items = function_exists('getList')
    ? getList("SELECT * FROM tbl_jobwork_order_items WHERE jobwork_order_id = $id ORDER BY id ASC")
    : [];
if (!is_array($items)) {
    $items = [];
}

foreach ($items as &$item) {
    if (!is_array($item)) {
        continue;
    }
    $display = auragold_mfg_jobwork_line_calculated_total_wt($item, $ji_cols);
    $item['queue_display_total_wt'] = $display;
    $item['queue_display_loss_wt'] = auragold_mfg_jobwork_line_queue_display_loss($item, $ji_cols, $display);
}
unset($item);

$issue_tbl = 'tbl_jobwork_queue_diamond_stock_issue';
$issue_chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $issue_tbl) . "'");
if ($issue_chk && mysqli_num_rows($issue_chk) > 0) {
    mysqli_free_result($issue_chk);
    $issue_rows = function_exists('getList')
        ? getList(
            'SELECT jobwork_order_item_id, COALESCE(SUM(`weight`), 0) AS issue_wt'
            . ' FROM `' . $issue_tbl . '`'
            . ' WHERE jobwork_order_id = ' . (int) $id
            . ' AND jobwork_order_item_id IS NOT NULL AND jobwork_order_item_id > 0'
            . ' GROUP BY jobwork_order_item_id'
        )
        : [];
    if (is_array($issue_rows) && $issue_rows !== []) {
        $issue_by_item = [];
        foreach ($issue_rows as $ir) {
            $iid = (int) ($ir['jobwork_order_item_id'] ?? 0);
            if ($iid > 0) {
                $issue_by_item[$iid] = (float) ($ir['issue_wt'] ?? 0);
            }
        }
        foreach ($items as &$item) {
            if (!is_array($item)) {
                continue;
            }
            $iid = (int) ($item['id'] ?? 0);
            if ($iid < 1 || empty($issue_by_item[$iid])) {
                continue;
            }
            $issueWt = (float) $issue_by_item[$iid];
            if ($issueWt <= 0.0000001) {
                continue;
            }
            $metal = auragold_mfg_jobwork_line_original_metal_grams($item, $ji_cols);
            $lo = auragold_mfg_jobwork_line_saved_loss_grams($item, $ji_cols);
            if (!is_finite($lo) || $lo < 0) {
                $lo = 0.0;
            }
            $lineD = auragold_mfg_jobwork_line_diamond_grams($item, $ji_cols);
            $dEff = max($lineD, $issueWt);
            $formula = round(max(0.0, $metal - $lo + $dEff), 4);
            $cur = (float) ($item['queue_display_total_wt'] ?? 0);
            if ($formula > $cur + 0.0001) {
                $item['queue_display_total_wt'] = $formula;
            }
        }
        unset($item);
    }
} elseif ($issue_chk) {
    mysqli_free_result($issue_chk);
}

echo json_encode(['ok' => true, 'items' => $items]);
