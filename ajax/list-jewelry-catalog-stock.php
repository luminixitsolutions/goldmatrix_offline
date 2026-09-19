<?php
/**
 * JSON: saved Jewellery Catalogue records (tbl_jewelry_catalogue) for the grid/list.
 */
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/../includes/jewelry_catalogue_create_include.php';

function jcat_json_out(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    echo ($json !== false) ? $json : '{"success":false,"message":"JSON encode failed"}';
    exit;
}

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    jcat_json_out(['success' => false, 'message' => 'Unauthorized']);
}

$opts = [
    'metal_id' => isset($_GET['metal_id']) ? (int) $_GET['metal_id'] : 0,
    'branch_id' => isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0,
    'product_id' => isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0,
    'category_id' => isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0,
    'q' => isset($_GET['q']) ? (string) $_GET['q'] : '',
    'article' => isset($_GET['article']) ? (string) $_GET['article'] : '',
    'barcode' => isset($_GET['barcode']) ? (string) $_GET['barcode'] : '',
    'design_no' => isset($_GET['design_no']) ? (string) $_GET['design_no'] : '',
    'location' => isset($_GET['location']) ? (string) $_GET['location'] : '',
    'comment' => isset($_GET['comment']) ? (string) $_GET['comment'] : '',
    'gross_wt' => isset($_GET['gross_wt']) ? (string) $_GET['gross_wt'] : '',
    'rfid_code' => isset($_GET['rfid_code']) ? (string) $_GET['rfid_code'] : '',
    'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 5000,
];

try {
    auragold_jewelry_catalogue_boot_stock_helpers();
    $items = auragold_jewelry_catalogue_grid_fetch($conn, $opts, $SiteUrl ?? '');
    $metals = auragold_jewelry_catalog_metals($conn);

    jcat_json_out([
        'success' => true,
        'items' => $items,
        'metals' => $metals,
        'total' => count($items),
    ]);
} catch (Throwable $e) {
    jcat_json_out(['success' => false, 'message' => $e->getMessage()]);
}
