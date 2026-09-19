<?php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_material_issue_rows_for_sale_order.php';

header('Content-Type: application/json; charset=utf-8');

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$sale_order_id = isset($_GET['sale_order_id']) ? (int) $_GET['sale_order_id'] : 0;
$repair = isset($_GET['from_repair']) && ($_GET['from_repair'] === '1' || $_GET['from_repair'] === 'true');
$gem = isset($_GET['gem']) ? strtolower(trim((string) $_GET['gem'])) : 'diamond';

if ($sale_order_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'sale_order_id required']);
    exit;
}

$metal_exchange = [];
if ($repair) {
    $diamonds = auragold_material_issue_list_diamond_rows_for_repair_order($conn, $sale_order_id);
    $stones = auragold_material_issue_list_stone_rows_for_repair_order($conn, $sale_order_id);
} else {
    $mi_scope = function_exists('auragold_effective_branch_list_scope_sql')
        ? auragold_effective_branch_list_scope_sql($conn, 'tbl_material_issues')
        : '';
    try {
        $diamonds = auragold_material_issue_list_diamond_rows_for_sale_order($conn, $sale_order_id, $mi_scope);
    } catch (Throwable $e) {
        error_log('list-material-receive-issued-reference diamonds: ' . $e->getMessage());
        $diamonds = [];
    }
    try {
        $stones = auragold_material_issue_list_stone_rows_for_sale_order($conn, $sale_order_id, $mi_scope);
    } catch (Throwable $e) {
        error_log('list-material-receive-issued-reference stones: ' . $e->getMessage());
        $stones = [];
    }
    if (function_exists('auragold_material_issue_list_metal_exchange_rows_for_sale_order')) {
        try {
            $metal_exchange = auragold_material_issue_list_metal_exchange_rows_for_sale_order(
                $conn,
                $sale_order_id,
                $mi_scope,
                true
            );
            if ($metal_exchange === [] && $mi_scope !== '') {
                $metal_exchange = auragold_material_issue_list_metal_exchange_rows_for_sale_order(
                    $conn,
                    $sale_order_id,
                    '',
                    true
                );
            }
        } catch (Throwable $e) {
            error_log('list-material-receive-issued-reference metal_exchange: ' . $e->getMessage());
            $metal_exchange = [];
        }
        if ($metal_exchange === [] && function_exists('auragold_material_issue_resolve_mi_ids_for_sale_order')) {
            $mi_dbg = auragold_material_issue_resolve_mi_ids_for_sale_order($conn, $sale_order_id, $mi_scope);
            $stock_dbg = 0;
            if ($mi_dbg !== [] && function_exists('auragold_material_issue_fetch_me_stock_rows_for_mi_ids')) {
                $stock_dbg = count(auragold_material_issue_fetch_me_stock_rows_for_mi_ids($conn, $mi_dbg));
            }
            error_log(
                'list-material-receive-issued-reference: empty metal_exchange for sale_order_id='
                . $sale_order_id
                . ' mi_ids='
                . json_encode($mi_dbg)
                . ' stock_rows='
                . $stock_dbg
            );
        }
    }
}

$payload = [
    'ok' => true,
    'diamonds' => $diamonds,
    'stones' => $stones,
    'metal_exchange' => $metal_exchange,
    'gem' => $gem,
];
if (!$repair && isset($_GET['debug']) && $_GET['debug'] === '1' && function_exists('auragold_material_issue_resolve_mi_ids_for_sale_order')) {
    $mi_dbg = auragold_material_issue_resolve_mi_ids_for_sale_order($conn, $sale_order_id, $mi_scope);
    $stock_dbg = [];
    if ($mi_dbg !== [] && function_exists('auragold_material_issue_fetch_me_stock_rows_for_mi_ids')) {
        $stock_dbg = auragold_material_issue_fetch_me_stock_rows_for_mi_ids($conn, $mi_dbg);
    }
    $payload['me_debug'] = [
        'sale_order_id' => $sale_order_id,
        'mi_scope_sql' => $mi_scope,
        'mi_ids' => $mi_dbg,
        'stock_row_count' => is_array($stock_dbg) ? count($stock_dbg) : 0,
        'metal_exchange_count' => count($metal_exchange),
        'has_stock_reference_columns' => function_exists('auragold_material_issue_stock_has_reference_columns')
            ? auragold_material_issue_stock_has_reference_columns($conn)
            : null,
    ];
}
echo json_encode($payload);
