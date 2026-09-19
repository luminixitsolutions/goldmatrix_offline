<?php

session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if (is_file(__DIR__ . '/../includes/auragold_branch_data_scope.php')) {
    require_once __DIR__ . '/../includes/auragold_branch_data_scope.php';
}

// Add Diamond modal: loose Diamonds + GemStones only (not Jewellery-category stock).
$gas_diamond_stock_pc_scope = 'diamond_stone_only';
require_once __DIR__ . '/../includes/diamond_stock_list_sql_include.php';
require_once __DIR__ . '/../includes/auragold_sale_order_diamond_list_journal.php';

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$sql = $diamond_stock_list_sql;
if ($search !== '') {
    $e = mysqli_real_escape_string($conn, $search);
    $sql = preg_replace(
        '/\s+ORDER BY s\.barcode ASC/i',
        " AND (s.barcode LIKE '%$e%' OR p.name LIKE '%$e%' OR COALESCE(p.article,'') LIKE '%$e%') ORDER BY s.barcode ASC",
        $sql
    );
}
$sql .= ' LIMIT 800';

if (!$has_stock) {
    echo json_encode(['ok' => false, 'message' => 'Stock table not found.', 'items' => []]);
    exit;
}
$res = mysqli_query($conn, $sql);
if (!$res) {
    echo json_encode(['ok' => false, 'message' => mysqli_error($conn), 'items' => []]);
    exit;
}
$raw = [];
while ($r = mysqli_fetch_assoc($res)) {
    $raw[] = $r;
}
mysqli_free_result($res);

$rate_by_id = [];
$id_keys = [];
foreach ($raw as $r) {
    $sid = (int) ($r['stock_id'] ?? 0);
    if ($sid > 0) {
        $id_keys[$sid] = true;
    }
}
if ($id_keys !== []) {
    $ids_sql = implode(',', array_map('intval', array_keys($id_keys)));
    $rq = @mysqli_query($conn, 'SELECT id, rate FROM tbl_stock WHERE id IN (' . $ids_sql . ')');
    if ($rq) {
        while ($x = mysqli_fetch_assoc($rq)) {
            $rate_by_id[(int) ($x['id'] ?? 0)] = (float) ($x['rate'] ?? 0);
        }
        mysqli_free_result($rq);
    }
}

$bc_for_journal = [];
foreach ($raw as $r) {
    $b = trim((string) ($r['barcode'] ?? ''));
    if ($b !== '') {
        $bc_for_journal[] = $b;
    }
}
$jmap = auragold_sale_order_diamond_journal_by_barcodes($conn, $bc_for_journal);

$rows = [];
foreach ($raw as $r) {
    $sid = (int) ($r['stock_id'] ?? 0);
    $bc = trim((string) ($r['barcode'] ?? ''));
    $jx = ($bc !== '' && isset($jmap[$bc])) ? $jmap[$bc] : [];

    $carat = '';
    if (isset($r['sj_karat']) && $r['sj_karat'] !== null && trim((string) $r['sj_karat']) !== '') {
        $carat = (string) $r['sj_karat'];
    } else {
        $carat = (string) ($r['pc_carat'] ?? '');
    }

    $ss = $r['stock_status'] ?? null;
    $active = ($ss === 1 || $ss === '1' || strtolower((string) $ss) === 'active');

    $style = trim((string) ($jx['style'] ?? ''));
    if ($style === '') {
        $style = trim((string) ($r['article'] ?? ''));
    }

    $rows[] = [
        'stock_id' => $sid,
        'barcode' => $bc,
        'product_name' => (string) ($r['product_name'] ?? ''),
        'article' => (string) ($r['article'] ?? ''),
        'diamond_category' => (string) ($r['category_display'] ?? ''),
        'metal_name' => (string) ($r['metal_name'] ?? ''),
        'current_qty' => (float) ($r['current_qty'] ?? 0),
        'current_weight' => (float) ($r['current_weight'] ?? 0),
        'rate' => $rate_by_id[$sid] ?? 0.0,
        'diamond_carat' => $carat,
        'active' => $active ? 'Active' : 'Inactive',
        'style' => $style,
        'calculation_type' => (string) ($jx['calculation'] ?? ''),
        'certificate_no' => (string) ($jx['certificate_no'] ?? ''),
        'cut' => (string) ($jx['cut'] ?? ''),
        'color' => (string) ($jx['color'] ?? ''),
        'seivesize' => (string) ($jx['seivesize'] ?? ''),
        'size' => (string) ($jx['size'] ?? ''),
        'shape' => (string) ($jx['shape'] ?? ''),
        'quality' => (string) ($jx['quality'] ?? ''),
    ];
}

echo json_encode(['ok' => true, 'items' => $rows], JSON_UNESCAPED_UNICODE);
