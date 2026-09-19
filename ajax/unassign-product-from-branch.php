<?php
/**
 * Sub-branch only: remove product from this branch (tbl_product_branches row).
 * Does not delete tbl_products or stock.
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_product_catalog_scope.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid request']);
    exit;
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['status' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
if ($product_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid product ID']);
    exit;
}

if (!($conn instanceof mysqli)) {
    echo json_encode(['status' => false, 'message' => 'Database connection not available.']);
    exit;
}

try {
    $payload = auragold_with_product_catalog_conn($conn, static function (array $ctx) use ($product_id) {
        if (empty($ctx['is_sub']) || (int) ($ctx['sub_branch_id'] ?? 0) <= 0) {
            return ['status' => false, 'message' => 'This action is only available on a sub-branch.'];
        }

        global $conn;
        $tb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_product_branches'");
        if (!$tb || mysqli_num_rows($tb) === 0) {
            if ($tb) {
                mysqli_free_result($tb);
            }
            return ['status' => false, 'message' => 'Product branch assignment table is not available.'];
        }
        mysqli_free_result($tb);

        $sub_id = (int) ($ctx['sub_branch_id'] ?? 0);
        $exists = getRecord(
            "SELECT 1 AS ok FROM tbl_product_branches WHERE branch_id = $sub_id AND product_id = $product_id LIMIT 1"
        );
        if (!$exists) {
            return ['status' => false, 'message' => 'This product is not assigned to your branch.'];
        }

        if (!mysqli_query(
            $conn,
            "DELETE FROM tbl_product_branches WHERE branch_id = $sub_id AND product_id = $product_id"
        )) {
            return ['status' => false, 'message' => mysqli_error($conn)];
        }

        return [
            'status'  => true,
            'message' => 'Product removed from this branch. It remains on the main branch.',
        ];
    });
} catch (Throwable $e) {
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
    exit;
}

echo json_encode($payload);
