<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/barcode_management_fetch.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

$tab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'all';
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = max(10, min(200, (int) ($_GET['per_page'] ?? 20)));
$search = trim((string) ($_GET['q'] ?? ''));
$summary_only = !empty($_GET['summary']);

$fetch_opts = [
    'status_filter' => 'present',
    'paginate' => true,
    'fast_paginate' => true,
    'page' => $page,
    'per_page' => $per_page,
    'search' => $search,
    'include_summary' => false,
    'skip_rows' => false,
];

if ($summary_only) {
    $fetch = auragold_barcode_management_fetch($conn, $tab, [
        'status_filter' => 'present',
        'paginate' => true,
        'fast_paginate' => true,
        'page' => 1,
        'per_page' => 20,
        'search' => $search,
        'include_summary' => true,
        'skip_rows' => true,
    ]);
    if (($fetch['error'] ?? '') !== '') {
        echo json_encode(['status' => 'error', 'message' => $fetch['error']]);
        exit;
    }
    $sv = is_array($fetch['summary'] ?? null) ? $fetch['summary'] : [];
    echo json_encode([
        'status' => 'success',
        'summary' => [
            'total_count' => (int) ($sv['total_count'] ?? 0),
            'qty_sum' => (float) ($sv['qty_sum'] ?? 0),
            'wt_sum' => (float) ($sv['wt_sum'] ?? 0),
            'metal_counts' => is_array($sv['metal_counts'] ?? null) ? $sv['metal_counts'] : [],
        ],
    ]);
    exit;
}

$fetch = auragold_barcode_management_fetch($conn, $tab, $fetch_opts);
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

ob_start();
if (empty($rows)) {
    echo '<tr><td colspan="16" class="sv-empty">No available barcode stock for this metal.</td></tr>';
} else {
    foreach ($rows as $r) {
        $gw = $r['sj_gross_weight'] ?? $r['opening_weight'] ?? null;
        $nw = $r['sj_net_weight'] ?? null;
        $carat = $r['pc_carat'] ?? $r['sj_karat'] ?? '';
        $bc = trim((string) ($r['barcode'] ?? ''));
        $print_href = $bc !== '' ? ('barcode-print.php?barcode=' . rawurlencode($bc)) : '';
        $search_blob = strtolower(implode(' ', array_filter([
            $bc,
            (string) ($r['product_name'] ?? ''),
            (string) ($r['category_display'] ?? ''),
            (string) ($r['invoice_no'] ?? ''),
            (string) ($r['metal_name'] ?? ''),
            (string) ($r['huid_no'] ?? ''),
        ])));
        $gw_num = (float) ($gw ?? 0);
        $nw_num = (float) ($nw ?? 0);
        $bal_qty_num = (float) ($r['current_qty'] ?? 0);
        $bal_wt_num = (float) ($r['current_weight'] ?? 0);
        ?>
        <tr data-sv-search="<?php echo htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8'); ?>"
            data-sv-gross-wt="<?php echo htmlspecialchars((string) $gw_num, ENT_QUOTES, 'UTF-8'); ?>"
            data-sv-net-wt="<?php echo htmlspecialchars((string) $nw_num, ENT_QUOTES, 'UTF-8'); ?>"
            data-sv-bal-qty="<?php echo htmlspecialchars((string) $bal_qty_num, ENT_QUOTES, 'UTF-8'); ?>"
            data-sv-bal-wt="<?php echo htmlspecialchars((string) $bal_wt_num, ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $bc !== '' ? ' data-sv-barcode="' . htmlspecialchars($bc, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
            <td class="sv-check-col">
                <?php if ($bc !== ''): ?>
                    <input type="checkbox" class="sv-check sv-row-check" value="<?php echo htmlspecialchars($bc, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Select <?php echo htmlspecialchars($bc, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
            </td>
            <td class="sv-metal"><?php echo htmlspecialchars((string) ($r['metal_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><strong><?php echo htmlspecialchars($bc, ENT_QUOTES, 'UTF-8'); ?></strong></td>
            <td><?php echo htmlspecialchars((string) ($r['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($r['category_display'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($r['branch_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(function_exists('gas_fmt_num') ? gas_fmt_num($gw, 3) : (string) $gw, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(function_exists('gas_fmt_num') ? gas_fmt_num($nw, 3) : (string) $nw, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(function_exists('gas_fmt_num') ? gas_fmt_num($r['current_qty'] ?? null, 2) : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars(function_exists('gas_fmt_num') ? gas_fmt_num($r['current_weight'] ?? null, 3) : '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) $carat, ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($r['huid_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($r['sj_location'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($r['voucher_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo htmlspecialchars((string) ($r['invoice_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
            <td>
                <?php if ($print_href !== ''): ?>
                    <a class="sv-print" href="<?php echo htmlspecialchars($print_href, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Print</a>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }
}
$rows_html = ob_get_clean();

$cur_page = (int) ($pg['page'] ?? 1);
$total_pages = max(1, (int) ($pg['total_pages'] ?? 1));
$pg_total = (int) ($pg['total'] ?? 0);
$pg_per = (int) ($pg['per_page'] ?? $per_page);
$range_from = $pg_total > 0 ? (($cur_page - 1) * $pg_per + 1) : 0;
$range_to = min($pg_total, $cur_page * $pg_per);

echo json_encode([
    'status' => 'success',
    'rows_html' => $rows_html,
    'pagination' => [
        'page' => $cur_page,
        'per_page' => $pg_per,
        'total' => $pg_total,
        'total_pages' => $total_pages,
        'range_from' => $range_from,
        'range_to' => $range_to,
    ],
]);
