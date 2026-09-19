<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_analysis_show_in_stock_sql.php';
require_once __DIR__ . '/../includes/gold_silver_analysis_helpers.php';
require_once __DIR__ . '/../includes/diamond_stone_analysis_roll_up_include.php';
require_once __DIR__ . '/../includes/diamond_stone_analysis_load.php';
require_once __DIR__ . '/../includes/diamond_stone_analysis_render.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

$active_tab = isset($_GET['tab']) ? (string) $_GET['tab'] : 'current-stock';
if (!in_array($active_tab, ['current-stock', 'stock-details'], true)) {
    $active_tab = 'current-stock';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = max(10, min(100, (int) ($_GET['per_page'] ?? 20)));
$summary_only = !empty($_GET['summary']);

try {
    if ($summary_only) {
        $fetch = auragold_dsa_fetch_table_data($conn, $active_tab, $page, $per_page, [
            'include_totals' => true,
            'skip_rows' => true,
        ]);
        echo json_encode([
            'status' => 'success',
            'totals' => $fetch['totals'],
            'active_tab' => $active_tab,
            'tfoot_cells_html' => $active_tab === 'stock-details'
                ? dsa_render_stock_details_tfoot_cells($fetch['totals'])
                : '',
        ]);
        exit;
    }

    $fetch = auragold_dsa_fetch_table_data($conn, $active_tab, $page, $per_page, [
        'include_totals' => false,
        'skip_rows' => false,
    ]);

    $stock_data = $fetch['stock_data'];
    $total_stock = (int) $fetch['total_stock'];
    $total_pages = (int) $fetch['total_pages'];
    $offset = (int) $fetch['offset'];
    $range_from = $total_stock > 0 ? ($offset + 1) : 0;
    $range_to = min($offset + $per_page, $total_stock);

    if ($active_tab === 'current-stock') {
        $tbody_html = dsa_render_current_stock_tbody($stock_data);
    } else {
        $tbody_html = dsa_render_stock_details_tbody($stock_data);
    }

    echo json_encode([
        'status' => 'success',
        'active_tab' => $active_tab,
        'tbody_html' => $tbody_html,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total_stock,
            'total_pages' => $total_pages,
            'range_from' => $range_from,
            'range_to' => $range_to,
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not load analysis data.',
    ]);
}
