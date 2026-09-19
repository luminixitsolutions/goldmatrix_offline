<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/gold_silver_stock_list_fetch.php';
require_once __DIR__ . '/../includes/gold_and_silver_table_context.php';
require_once __DIR__ . '/../includes/gold_and_silver_table_render.php';

if (ob_get_level() > 0) {
    ob_clean();
} else {
    ob_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

$tab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'gold';
if (!in_array($tab, ['gold', 'silver', 'all'], true)) {
    $tab = 'gold';
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = max(10, min(100, (int) ($_GET['per_page'] ?? 20)));

$meta = auragold_gold_silver_stock_list_fetch($conn, $tab, ['meta_only' => true]);
$extra_field_defs = is_array($meta['extra_field_defs'] ?? null) ? $meta['extra_field_defs'] : [];
$has_journal_images = !empty($meta['has_journal_images']);

$table_ctx = gas_build_stock_list_table_context($conn, $tab, $extra_field_defs);
$render_ctx = gas_stock_list_render_ctx($table_ctx, $extra_field_defs, $has_journal_images);

$fetch = auragold_gold_silver_stock_list_fetch($conn, $tab, [
    'paginate' => true,
    'fast_paginate' => true,
    'page' => $page,
    'per_page' => $per_page,
    'skip_rows' => false,
]);

if (($fetch['error'] ?? '') !== '') {
    echo json_encode(['status' => 'error', 'message' => $fetch['error']]);
    exit;
}

$rows = is_array($fetch['rows'] ?? null) ? $fetch['rows'] : [];
$pg = is_array($fetch['pagination'] ?? null) ? $fetch['pagination'] : [
    'page' => $page,
    'per_page' => $per_page,
    'total' => 0,
    'total_pages' => 1,
];

$tbody_html = gas_stock_list_render_tbody_rows($rows, $render_ctx, '');

$gas_grand = gas_stock_list_empty_grand_totals();
$wastage_per_sum = 0.0;
$wastage_per_cnt = 0;
foreach ($rows as $r) {
    gas_stock_list_accumulate_row_totals($gas_grand, $wastage_per_sum, $wastage_per_cnt, $r);
}
$wastage_avg = ($wastage_per_cnt > 0) ? ($wastage_per_sum / $wastage_per_cnt) : null;
$tfoot_cells_html = gas_stock_list_render_tfoot_cells($gas_grand, $wastage_avg, $render_ctx, false, 'Page Total');

$cur_page = (int) ($pg['page'] ?? 1);
$total_pages = max(1, (int) ($pg['total_pages'] ?? 1));
$pg_total = (int) ($pg['total'] ?? 0);
$pg_per = (int) ($pg['per_page'] ?? $per_page);
$range_from = $pg_total > 0 ? (($cur_page - 1) * $pg_per + 1) : 0;
$range_to = min($pg_total, $cur_page * $pg_per);

echo json_encode([
    'status' => 'success',
    'tbody_html' => $tbody_html,
    'tfoot_cells_html' => $tfoot_cells_html,
    'pagination' => [
        'page' => $cur_page,
        'per_page' => $pg_per,
        'total' => $pg_total,
        'total_pages' => $total_pages,
        'range_from' => $range_from,
        'range_to' => $range_to,
    ],
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
