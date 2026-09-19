<?php
/**
 * SQL fragments for "products assigned to a branch" (tbl_product_branches or tbl_product_characteristics).
 */

if (!function_exists('auragold_product_catalog_mysqli_context')) {
    /**
     * mysqli for tbl_products / tbl_product_branches (and related product rows).
     * Sub-branch sessions use the parent main operational database.
     *
     * @return array{ok:bool,link:?mysqli,close_after:bool,is_sub:bool,main_branch_id:int,sub_branch_id:int,message:string}
     */
    function auragold_product_catalog_mysqli_context(mysqli $sessionConn): array {
        if (!function_exists('auragold_product_opening_mysqli_for_login')) {
            require_once __DIR__ . '/auragold_product_branch_login_context.php';
        }
        return auragold_product_opening_mysqli_for_login($sessionConn);
    }
}

if (!function_exists('auragold_with_product_catalog_conn')) {
    /**
     * Run a callback while global $conn points at the product catalog database.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    function auragold_with_product_catalog_conn(mysqli $sessionConn, callable $fn) {
        $ctx = auragold_product_catalog_mysqli_context($sessionConn);
        if (empty($ctx['ok']) || !($ctx['link'] instanceof mysqli)) {
            $msg = trim((string) ($ctx['message'] ?? ''));
            throw new RuntimeException($msg !== '' ? $msg : 'Could not open product catalog database.');
        }
        $prev = $GLOBALS['conn'] ?? null;
        $GLOBALS['conn'] = $ctx['link'];
        try {
            return $fn($ctx);
        } finally {
            $GLOBALS['conn'] = $prev;
            if (!empty($ctx['close_after']) && $ctx['link'] instanceof mysqli) {
                mysqli_close($ctx['link']);
            }
        }
    }
}

if (!function_exists('auragold_sql_products_scope_for_branch')) {
    /**
     * Correlated predicate for tbl_products: product is linked to the given branch.
     *
     * @param int $branch_id Branch registry id (e.g. main branch id)
     */
    function auragold_sql_products_scope_for_branch($branch_id) {
        $branch_id = (int) $branch_id;
        if ($branch_id <= 0) {
            return '0';
        }
        return "(EXISTS (SELECT 1 FROM tbl_product_branches pb WHERE pb.product_id = tbl_products.id AND pb.branch_id = $branch_id AND IFNULL(pb.is_active, 1) = 1) "
            . "OR EXISTS (SELECT 1 FROM tbl_product_characteristics pc WHERE pc.product_id = tbl_products.id AND pc.status = 1 AND pc.branch_id = $branch_id))";
    }
}
