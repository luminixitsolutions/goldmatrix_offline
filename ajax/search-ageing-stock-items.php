<?php
/**
 * Product list for Ageing Report — Stock tab typeahead (tbl_products).
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$q_sql = function_exists('esc') ? esc($q) : mysqli_real_escape_string($conn, $q);

$limit = 42;
$items = [];

if (!function_exists('getList')) {
    echo json_encode(['status' => 'error', 'items' => []]);
    exit;
}

if (strlen($q_sql) < 1) {
    $sql = 'SELECT id, name, article FROM tbl_products WHERE status = 1 ORDER BY name ASC LIMIT ' . (int) $limit;
} else {
    $sql = "SELECT id, name, article FROM tbl_products WHERE status = 1
        AND (name LIKE '%$q_sql%' OR IFNULL(article,'') LIKE '%$q_sql%')
        ORDER BY
            CASE WHEN name LIKE '$q_sql%' THEN 0 ELSE 1 END,
            name ASC
        LIMIT " . (int) $limit;
}

$rows = getList($sql);
if (is_array($rows)) {
    foreach ($rows as $r) {
        $items[] = [
            'id' => (int) ($r['id'] ?? 0),
            'name' => (string) ($r['name'] ?? ''),
            'article' => (string) ($r['article'] ?? ''),
        ];
    }
}

echo json_encode(['status' => 'success', 'items' => $items]);
