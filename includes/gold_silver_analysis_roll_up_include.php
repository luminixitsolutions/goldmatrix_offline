<?php

/**
 * Gold/Silver analysis — shared roll-up SQL.
 * Included by gold-silver-analysis.php and stock-details export endpoints.
 */

if (!function_exists('auragold_gsa_ensure_stock_query_conn')) {
    /**
     * Sub-branch opening stock (tbl_stock, characteristics, branch settings) is stored in the
     * parent main operational database. When the session uses a separate sub-branch DB, point
     * global $conn at the catalog DB for analysis queries (still filtered by sub branch_id).
     */
    function auragold_gsa_ensure_stock_query_conn(): void {
        global $conn;

        static $bootstrapped = false;
        if ($bootstrapped) {
            return;
        }
        $bootstrapped = true;

        if (!($conn instanceof mysqli)) {
            return;
        }

        if (!function_exists('auragold_product_catalog_mysqli_context')) {
            require_once __DIR__ . '/auragold_product_catalog_scope.php';
        }

        $sessionConn = $conn;
        $ctx = auragold_product_catalog_mysqli_context($sessionConn);
        if (empty($ctx['ok']) || !($ctx['link'] instanceof mysqli)) {
            return;
        }

        $catalogConn = $ctx['link'];
        if ($catalogConn === $sessionConn) {
            if (!empty($ctx['close_after'])) {
                mysqli_close($catalogConn);
            }
            return;
        }

        $GLOBALS['auragold_gsa_stock_session_conn'] = $sessionConn;
        $GLOBALS['auragold_gsa_stock_catalog_conn'] = $catalogConn;
        $conn = $catalogConn;
        $GLOBALS['conn'] = $catalogConn;

        register_shutdown_function(static function (): void {
            global $conn;
            $session = $GLOBALS['auragold_gsa_stock_session_conn'] ?? null;
            $catalog = $GLOBALS['auragold_gsa_stock_catalog_conn'] ?? null;
            if ($session instanceof mysqli) {
                $conn = $session;
                $GLOBALS['conn'] = $session;
            }
            if ($catalog instanceof mysqli && $catalog !== $session) {
                mysqli_close($catalog);
            }
            unset($GLOBALS['auragold_gsa_stock_session_conn'], $GLOBALS['auragold_gsa_stock_catalog_conn']);
        });
    }
}

auragold_gsa_ensure_stock_query_conn();

// Search and filters
$search_raw = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$search_term = $search_raw !== '' ? esc($search_raw) : '';
$branch_ids = [];
if (isset($_GET['branch'])) {
    if (is_array($_GET['branch'])) {
        $branch_ids = array_values(array_unique(array_filter(array_map('intval', $_GET['branch']))));
    } else {
        $b = (int) $_GET['branch'];
        if ($b > 0) {
            $branch_ids = [$b];
        }
    }
}

$gsa_effective_branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
// Scope to login branch when no branch filter in URL; use ?branch=0 (All Branches) for login main + its sub-branches.
if (empty($branch_ids) && $gsa_effective_branch_id > 0 && !isset($_GET['branch'])) {
    $branch_ids = [$gsa_effective_branch_id];
}

// Filter dropdown + query scope: login main branch and its sub-branches only (not other company mains).
$gsa_tree_root_id = function_exists('auragold_branch_stock_transfer_tree_root_id')
    ? (int) auragold_branch_stock_transfer_tree_root_id()
    : (function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0);

$gsa_sub_branch_login = false;
$gsa_sub_branch_id = 0;
$gsa_login_br = null;
if ($gsa_effective_branch_id > 0 && function_exists('getRecordMaster')) {
    $gsa_login_br = getRecordMaster(
        'SELECT id, name, IFNULL(main_branch_id, 0) AS mb FROM tbl_branches WHERE id = '
        . (int) $gsa_effective_branch_id . ' AND status = 1 LIMIT 1'
    );
}
// Sub-branch session: not the tree-root main (e.g. Developer Branch under gm), or registry main_branch_id set.
if ($gsa_effective_branch_id > 0) {
    $gsa_is_registry_sub = ($gsa_login_br && (int) ($gsa_login_br['mb'] ?? 0) > 0);
    $gsa_is_tree_sub = ($gsa_tree_root_id > 0 && $gsa_effective_branch_id !== $gsa_tree_root_id);
    $gsa_is_catalog_sub = false;
    if ($conn instanceof mysqli) {
        if (!function_exists('auragold_product_catalog_mysqli_context')) {
            require_once __DIR__ . '/auragold_product_catalog_scope.php';
        }
        if (function_exists('auragold_product_catalog_mysqli_context')) {
            $gsa_cat_ctx = auragold_product_catalog_mysqli_context($conn);
            $gsa_is_catalog_sub = !empty($gsa_cat_ctx['is_sub']) && (int) ($gsa_cat_ctx['sub_branch_id'] ?? 0) === $gsa_effective_branch_id;
            if (!empty($gsa_cat_ctx['close_after']) && ($gsa_cat_ctx['link'] ?? null) instanceof mysqli && ($gsa_cat_ctx['link'] !== $conn)) {
                mysqli_close($gsa_cat_ctx['link']);
            }
        }
    }
    if ($gsa_is_registry_sub || $gsa_is_tree_sub || $gsa_is_catalog_sub) {
        $gsa_sub_branch_login = true;
        $gsa_sub_branch_id = $gsa_effective_branch_id;
    }
}

$gsa_allowed_branch_ids = [];
$branches = [];
if ($gsa_sub_branch_login && $gsa_sub_branch_id > 0) {
    // Sub-branch login: only this branch in filters and queries (ignore ?branch= / All Branches).
    $branch_ids = [$gsa_sub_branch_id];
    $gsa_sub_branch_name = $gsa_login_br ? trim((string) ($gsa_login_br['name'] ?? '')) : '';
    if ($gsa_sub_branch_name === '' && !empty($_SESSION['working_branch_name'])) {
        $gsa_sub_branch_name = trim((string) $_SESSION['working_branch_name']);
    }
    $branches = [[
        'id'   => $gsa_sub_branch_id,
        'name' => $gsa_sub_branch_name !== '' ? $gsa_sub_branch_name : ('Branch #' . $gsa_sub_branch_id),
    ]];
    $gsa_allowed_branch_ids = [$gsa_sub_branch_id];
} elseif ($gsa_tree_root_id > 0 && function_exists('getListMaster')) {
    $branches = getListMaster(
        'SELECT id, name FROM tbl_branches WHERE status = 1 AND (id = ' . $gsa_tree_root_id
        . ' OR IFNULL(main_branch_id, 0) = ' . $gsa_tree_root_id . ') ORDER BY name ASC'
    );
} elseif (function_exists('getListMaster')) {
    $branches = getListMaster('SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC');
}
if (!is_array($branches)) {
    $branches = [];
}
if (!$gsa_sub_branch_login) {
    $gsa_allowed_branch_ids = array_values(array_filter(array_map('intval', array_column($branches, 'id'))));
    if (!empty($gsa_allowed_branch_ids)) {
        if (!empty($branch_ids)) {
            $branch_ids = array_values(array_intersect($branch_ids, $gsa_allowed_branch_ids));
        }
        // All Branches / invalid selection: stay within login main + sub-branches only.
        if (empty($branch_ids)) {
            $branch_ids = $gsa_allowed_branch_ids;
        }
    }
}

$metal_filter_ids = [];
if (isset($_GET['metal'])) {
    if (is_array($_GET['metal'])) {
        $metal_filter_ids = array_values(array_unique(array_filter(array_map('intval', $_GET['metal']))));
    } else {
        $m = (int) $_GET['metal'];
        if ($m > 0) {
            $metal_filter_ids = [$m];
        }
    }
}

// Advance filter (modal) — same GET keys on apply
$adv_to_raw = isset($_GET['adv_to']) ? trim((string) $_GET['adv_to']) : '';
$adv_to_sql = '';
if ($adv_to_raw !== '') {
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $adv_to_raw)) {
        $adv_to_sql = $adv_to_raw;
    } elseif (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $adv_to_raw, $m)) {
        $adv_to_sql = $m[3] . '-' . $m[2] . '-' . $m[1];
    }
}
$adv_serial = isset($_GET['adv_serial']) ? strtolower(trim((string) $_GET['adv_serial'])) : 'both';
if (!in_array($adv_serial, ['both', 'yes', 'no'], true)) {
    $adv_serial = 'both';
}
$adv_product_ids = [];
if (isset($_GET['adv_product'])) {
    if (is_array($_GET['adv_product'])) {
        $adv_product_ids = array_values(array_unique(array_filter(array_map('intval', $_GET['adv_product']))));
    } else {
        $p = (int) $_GET['adv_product'];
        if ($p > 0) {
            $adv_product_ids = [$p];
        }
    }
}

$adv_articles = [];
if (isset($_GET['adv_article'])) {
    if (is_array($_GET['adv_article'])) {
        foreach ($_GET['adv_article'] as $a) {
            $a = trim((string) $a);
            if ($a !== '') {
                $adv_articles[] = $a;
            }
        }
    } else {
        $a = trim((string) $_GET['adv_article']);
        if ($a !== '') {
            $adv_articles[] = $a;
        }
    }
}
$adv_articles = array_values(array_unique($adv_articles));

$adv_karat_ids = [];
if (isset($_GET['adv_karat'])) {
    if (is_array($_GET['adv_karat'])) {
        $adv_karat_ids = array_values(array_unique(array_filter(array_map('intval', $_GET['adv_karat']))));
    } else {
        $k = (int) $_GET['adv_karat'];
        if ($k > 0) {
            $adv_karat_ids = [$k];
        }
    }
}

$adv_category_ids = [];
if (isset($_GET['adv_category'])) {
    if (is_array($_GET['adv_category'])) {
        $adv_category_ids = array_values(array_unique(array_filter(array_map('intval', $_GET['adv_category']))));
    } else {
        $c = (int) $_GET['adv_category'];
        if ($c > 0) {
            $adv_category_ids = [$c];
        }
    }
}

$adv_group = isset($_GET['adv_group']) ? trim((string) $_GET['adv_group']) : '';
$adv_gross_wt = isset($_GET['adv_gross']) ? trim((string) $_GET['adv_gross']) : '';

$gsa_stock_date_field = '';
$sdq = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_stock');
if ($sdq) {
    $date_candidates = ['created_at', 'created_on', 'updated_at', 'stock_date', 'transaction_date', 'entry_date'];
    while ($r = mysqli_fetch_assoc($sdq)) {
        $f = strtolower((string) ($r['Field'] ?? ''));
        if (in_array($f, $date_candidates, true)) {
            $gsa_stock_date_field = $r['Field'];
            break;
        }
    }
    mysqli_free_result($sdq);
}

$gsa_sj_has_group_name = false;
$gj = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_journal LIKE 'group_name'");
if ($gj && mysqli_num_rows($gj) > 0) {
    $gsa_sj_has_group_name = true;
}
if ($gj) {
    mysqli_free_result($gj);
}

// This page: Gold and Silver stock only (tbl_metal.display_name)
$scope_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 AND display_name IN ('Gold','Silver') ORDER BY display_name ASC");
$scope_metal_ids = array_map('intval', array_column($scope_metals ?: [], 'id'));
if (empty($scope_metal_ids)) {
    $scope_metal_ids = [1, 2];
}
if (empty($scope_metals) && !empty($scope_metal_ids)) {
    $scope_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 AND id IN (" . implode(',', $scope_metal_ids) . ") ORDER BY display_name ASC");
}

$metal_filter_ids = array_values(array_intersect($metal_filter_ids, $scope_metal_ids));
$branch_filter = count($branch_ids) === 1 ? (int) $branch_ids[0] : 0;
$metal_filter = count($metal_filter_ids) === 1 ? (int) $metal_filter_ids[0] : 0;

// Build WHERE clause
// Current Stock tab: display_qty/weight = receipt totals (opening + purchase + stock_journal + balance lots) minus outward — matches Stock Details pcs closing when the same tbl_stock rows are used.
$where_clause = "s.status = 1 AND s.stock_type IN ('opening', 'purchase', 'stock_journal', 'outward', 'balance', 'inward', 'sale_return')";
if ($search_term != '') {
    $where_clause .= " AND (p.name LIKE '%$search_term%' OR p.article LIKE '%$search_term%' OR p.alternate_name LIKE '%$search_term%')";
}
if (!empty($branch_ids)) {
    $where_clause .= ' AND s.branch_id IN (' . implode(',', array_map('intval', $branch_ids)) . ')';
}
$effective_metal_ids = !empty($metal_filter_ids) ? $metal_filter_ids : $scope_metal_ids;
$where_clause .= ' AND s.metal_id IN (' . implode(',', array_map('intval', $effective_metal_ids)) . ')';
// "Show In Stock" on Product Opening: tbl_product_branch_settings per (product, branch)
$where_clause .= ' AND ' . auragold_sql_show_in_stock_for_stock_table('s', 'p');

if ($adv_to_sql !== '' && $gsa_stock_date_field !== '') {
    $df = preg_replace('/[^a-zA-Z0-9_]/', '', $gsa_stock_date_field);
    if ($df !== '') {
        $adv_to_esc = esc($adv_to_sql);
        $where_clause .= " AND DATE(s.`$df`) <= '$adv_to_esc'";
    }
}
if ($adv_serial === 'yes') {
    $where_clause .= " AND s.barcode IS NOT NULL AND TRIM(s.barcode) != ''";
} elseif ($adv_serial === 'no') {
    $where_clause .= " AND (s.barcode IS NULL OR TRIM(s.barcode) = '')";
}
if (!empty($adv_product_ids)) {
    $where_clause .= ' AND s.product_id IN (' . implode(',', array_map('intval', $adv_product_ids)) . ')';
}
if (!empty($adv_articles)) {
    $parts = [];
    foreach ($adv_articles as $a) {
        $parts[] = "'" . esc($a) . "'";
    }
    $where_clause .= ' AND p.article IN (' . implode(',', $parts) . ')';
}
if (!empty($adv_karat_ids)) {
    $kn_parts = [];
    foreach ($adv_karat_ids as $kid) {
        $kr = getRecord('SELECT name FROM tbl_carat WHERE id = ' . (int) $kid . ' AND status = 1 LIMIT 1');
        if (!empty($kr['name'])) {
            $kn_parts[] = "'" . esc(trim((string) $kr['name'])) . "'";
        }
    }
    $kn_parts = array_unique($kn_parts);
    if (!empty($kn_parts)) {
        $where_clause .= ' AND pc.carat IN (' . implode(',', $kn_parts) . ')';
    }
}
if (!empty($adv_category_ids)) {
    $where_clause .= ' AND p.category_id IN (' . implode(',', array_map('intval', $adv_category_ids)) . ')';
}
if ($adv_group !== '' && $gsa_sj_has_group_name) {
    $g_like = '%' . esc(str_replace(['%', '_'], ['\\%', '\\_'], $adv_group)) . '%';
    $where_clause .= " AND EXISTS (
        SELECT 1 FROM tbl_stock_journal sj_grp
        WHERE sj_grp.status = 'active'
        AND sj_grp.group_name LIKE '$g_like'
        AND (
            sj_grp.product_id = s.product_id
            OR EXISTS (
                SELECT 1 FROM tbl_product_characteristics pcg
                WHERE pcg.id = sj_grp.product_characteristic_id
                AND pcg.product_id = s.product_id AND pcg.branch_id = s.branch_id AND pcg.metal_id = s.metal_id AND pcg.status = 1
            )
        )
    )";
}
if ($adv_gross_wt !== '' && is_numeric($adv_gross_wt)) {
    $gw = (float) $adv_gross_wt;
    $where_clause .= ' AND ABS(COALESCE(s.opening_weight, s.current_weight, 0) - ' . $gw . ') < 0.02';
}

$adv_filter_count = ($adv_to_sql !== '' ? 1 : 0)
    + ($adv_serial !== 'both' ? 1 : 0)
    + (!empty($branch_ids) ? 1 : 0)
    + (!empty($metal_filter_ids) ? 1 : 0)
    + (!empty($adv_product_ids) ? 1 : 0)
    + (!empty($adv_articles) ? 1 : 0)
    + (!empty($adv_karat_ids) ? 1 : 0)
    + (!empty($adv_category_ids) ? 1 : 0)
    + ($adv_group !== '' ? 1 : 0)
    + ($adv_gross_wt !== '' ? 1 : 0)
    + ($search_raw !== '' ? 1 : 0);

// Movement totals: opening_* = original receipt/issue on the row; inward − outward = balance.
$gsa_in_stock_types = "'opening','purchase','stock_journal','balance','inward','sale_return'";
$gsa_purity_factor = '(CASE WHEN COALESCE(s.opening_purity, 0) <= 1 THEN COALESCE(s.opening_purity, 0) ELSE COALESCE(s.opening_purity, 0) / 100 END)';
$gsa_mov_qty = 'COALESCE(NULLIF(s.opening_qty, 0), NULLIF(s.current_qty, 0), 0)';
$gsa_mov_wt = 'COALESCE(NULLIF(s.opening_weight, 0), NULLIF(s.current_weight, 0), 0)';
$gsa_mov_wt_abs = 'ABS(' . $gsa_mov_wt . ')';
$gsa_mov_qty_abs = 'ABS(' . $gsa_mov_qty . ')';

// Shared inner query: group by product + branch + metal + product_characteristic_id (used by Current Stock and Stock Details tabs).
// Aggregate from tbl_stock only (same scope as Stock Availability Wt). Do not filter purchase rows by
// tbl_purchase_invoice_items time-match — that hid valid lines (e.g. journal-linked purchase) and broke totals.
// Per-row qty/weight use COALESCE(NULLIF(current_* ,0), opening_*) so current_qty/current_weight = 0 still picks up opening_* (sold-down lots stay visible in totals).
// Inner subquery: $stock_inner_sql = $stock_inner_select . $stock_inner_from
//   SELECT ... FROM tbl_stock s LEFT JOIN ... WHERE ... GROUP BY s.product_id, s.branch_id, s.metal_id, s.product_characteristic_id
$gsa_join_location = '';
$gsa_loc_sql = "MAX('') AS location_name";
$gsa_has_loc_column = false;
$gsa_loc_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'location_id'");
$gsa_loc_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_location'");
$gsa_has_loc_table = ($gsa_loc_tbl && mysqli_num_rows($gsa_loc_tbl) > 0);
if ($gsa_loc_tbl) {
    mysqli_free_result($gsa_loc_tbl);
}
if ($gsa_loc_chk && mysqli_num_rows($gsa_loc_chk) > 0 && $gsa_has_loc_table) {
    $gsa_has_loc_column = true;
    $gsa_join_location = "\n    LEFT JOIN tbl_location loc_pc ON loc_pc.id = pc.location_id";
    $gsa_loc_sql = 'MAX(loc_pc.name) AS location_name';
}
if ($gsa_loc_chk) {
    mysqli_free_result($gsa_loc_chk);
}

$stock_inner_from = "
    FROM tbl_stock s
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_product_characteristics pc
        ON s.product_characteristic_id = pc.id
    $gsa_join_location
    WHERE $where_clause
    GROUP BY s.product_id, s.branch_id, s.metal_id, s.product_characteristic_id
";
$stock_inner_select = "
    SELECT 
        s.product_id,
        s.product_characteristic_id,
        s.branch_id,
        s.metal_id,
        MAX(p.name) as product_name,
        MAX(p.article) as article,
        MAX(p.alternate_name) as alternate_name,
        MAX(m.display_name) as metal_name,
        MAX(b.name) as branch_name,
        $gsa_loc_sql,
        MAX(pc.hsn) as hsn,
        MAX(pc.sku_code) as sku_code,
        MAX(pc.making_on) as making_on,
        MAX(pc.diamond_category) as diamond_category,
        MAX(pc.carat) as carat,
        SUM(
            CASE 
                WHEN s.stock_type IN ($gsa_in_stock_types)
                THEN $gsa_mov_qty
                ELSE 0
            END
        ) as purchase_qty,
        COALESCE((
            SELECT SUM(COALESCE(pii3.metal_weight, pii3.gross_weight, 0))
            FROM tbl_purchase_invoice_items pii3
            INNER JOIN tbl_product_characteristics pc3 ON pc3.id = pii3.product_characteristic_id AND pc3.product_id = s.product_id AND pc3.branch_id = s.branch_id AND pc3.metal_id = s.metal_id AND pc3.status = 1
            WHERE pii3.product_id = s.product_id AND pii3.status = 1
        ), 0) as purchase_metal_weight,
        COALESCE((
            SELECT SUM(sj.quantity)
            FROM tbl_stock_journal sj
            WHERE sj.status = 'active'
            AND (
                EXISTS (
                    SELECT 1 FROM tbl_purchase_invoice_items pii2
                    INNER JOIN tbl_product_characteristics pc2 ON pc2.id = pii2.product_characteristic_id
                    WHERE pii2.id = sj.item_id AND pii2.status = 1
                    AND pc2.product_id = s.product_id AND pc2.branch_id = s.branch_id AND pc2.metal_id = s.metal_id AND pc2.status = 1
                )
                OR EXISTS (
                    SELECT 1 FROM tbl_product_characteristics pc2
                    WHERE pc2.id = sj.product_characteristic_id
                    AND pc2.product_id = s.product_id AND pc2.branch_id = s.branch_id AND pc2.metal_id = s.metal_id AND pc2.status = 1
                )
            )
        ), 0) as production_qty,
        COALESCE((
            SELECT SUM(COALESCE(sj.gross_weight, sj.net_weight, 0))
            FROM tbl_stock_journal sj
            WHERE sj.status = 'active'
            AND (
                EXISTS (
                    SELECT 1 FROM tbl_purchase_invoice_items pii2
                    INNER JOIN tbl_product_characteristics pc2 ON pc2.id = pii2.product_characteristic_id
                    WHERE pii2.id = sj.item_id AND pii2.status = 1
                    AND pc2.product_id = s.product_id AND pc2.branch_id = s.branch_id AND pc2.metal_id = s.metal_id AND pc2.status = 1
                )
                OR EXISTS (
                    SELECT 1 FROM tbl_product_characteristics pc2
                    WHERE pc2.id = sj.product_characteristic_id
                    AND pc2.product_id = s.product_id AND pc2.branch_id = s.branch_id AND pc2.metal_id = s.metal_id AND pc2.status = 1
                )
            )
        ), 0) as production_weight,
        COALESCE((
            SELECT SUM(sii.quantity)
            FROM tbl_sale_invoice_items sii
            INNER JOIN tbl_sale_invoices si ON sii.invoice_id = si.id
            INNER JOIN tbl_product_characteristics pc4 ON pc4.id = sii.product_characteristic_id AND pc4.product_id = s.product_id AND pc4.branch_id = s.branch_id AND pc4.metal_id = s.metal_id AND pc4.status = 1
            WHERE sii.product_id = s.product_id AND sii.status = 1 AND si.status != 'cancelled'
        ), 0) as sale_invoice_qty,
        SUM(CASE WHEN s.stock_type IN ($gsa_in_stock_types) THEN $gsa_mov_wt ELSE 0 END) as inward_gross_sum,
        SUM(CASE WHEN s.stock_type IN ($gsa_in_stock_types) THEN $gsa_mov_wt * $gsa_purity_factor ELSE 0 END) as inward_pure_sum,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $gsa_mov_wt_abs * $gsa_purity_factor ELSE 0 END) as outward_pure_sum,
        SUM(
            CASE 
                WHEN s.stock_type IN ($gsa_in_stock_types)
                THEN $gsa_mov_qty
                ELSE 0
            END
        ) as available_qty,
        (SUM(CASE WHEN s.stock_type IN ($gsa_in_stock_types) THEN $gsa_mov_wt ELSE 0 END) - SUM(CASE WHEN s.stock_type = 'outward' THEN $gsa_mov_wt_abs ELSE 0 END)) as stock_net_weight,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $gsa_mov_wt_abs ELSE 0 END) as outward_weight_sum,
        SUM(CASE WHEN s.stock_type IN ($gsa_in_stock_types) THEN $gsa_mov_qty ELSE 0 END) as inward_receipt_qty_sum,
        SUM(CASE WHEN s.stock_type IN ($gsa_in_stock_types) THEN $gsa_mov_wt ELSE 0 END) as inward_receipt_weight_sum,
        SUM(CASE WHEN s.stock_type IN ($gsa_in_stock_types) THEN $gsa_mov_wt * $gsa_purity_factor ELSE 0 END) as inward_receipt_pure_sum,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $gsa_mov_qty_abs ELSE 0 END) as outward_qty_sum,
        SUM(s.opening_weight) as opening_weight,
        CASE WHEN SUM(CASE WHEN s.stock_type IN ($gsa_in_stock_types) THEN $gsa_mov_wt ELSE 0 END) > 0 THEN SUM(CASE WHEN s.stock_type IN ($gsa_in_stock_types) THEN $gsa_mov_wt * COALESCE(s.opening_purity, 0) ELSE 0 END) / SUM(CASE WHEN s.stock_type IN ($gsa_in_stock_types) THEN $gsa_mov_wt ELSE 0 END) ELSE MAX(s.opening_purity) END as opening_purity,
        SUM(CASE WHEN s.stock_type = 'opening' THEN $gsa_mov_wt ELSE 0 END) AS sd_gross_opening,
        SUM(CASE WHEN s.stock_type IN ('purchase','stock_journal','balance','inward','sale_return') THEN $gsa_mov_wt ELSE 0 END) AS sd_gross_in,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $gsa_mov_wt_abs ELSE 0 END) AS sd_gross_out,
        SUM(CASE WHEN s.stock_type = 'opening' THEN $gsa_mov_wt * $gsa_purity_factor ELSE 0 END) AS sd_pure_opening,
        SUM(CASE WHEN s.stock_type IN ('purchase','stock_journal','balance','inward','sale_return') THEN $gsa_mov_wt * $gsa_purity_factor ELSE 0 END) AS sd_pure_in,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $gsa_mov_wt_abs * $gsa_purity_factor ELSE 0 END) AS sd_pure_out,
        SUM(CASE WHEN s.stock_type = 'opening' THEN $gsa_mov_qty ELSE 0 END) AS sd_pcs_opening,
        SUM(CASE WHEN s.stock_type IN ('purchase','stock_journal','balance','inward','sale_return') THEN $gsa_mov_qty ELSE 0 END) AS sd_pcs_in,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $gsa_mov_qty_abs ELSE 0 END) AS sd_pcs_out,
        SUM(s.value) as value,
        MAX(s.final_weight) as final_weight,
        MAX(s.rate) as rate
";
$stock_inner_sql = $stock_inner_select . $stock_inner_from;

// Show In Stock products with no active tbl_stock row (zero opening / barcode-off saves still list in analysis).
$gsa_zero_stock_scope_branch = '';
if (!empty($branch_ids)) {
    $gsa_zero_stock_scope_branch = ' AND pbs.branch_id IN (' . implode(',', array_map('intval', $branch_ids)) . ')';
}
$gsa_zero_stock_scope_metal = ' AND pc.metal_id IN (' . implode(',', array_map('intval', $effective_metal_ids)) . ')';
$gsa_zero_stock_scope_search = '';
if ($search_term != '') {
    $gsa_zero_stock_scope_search = " AND (p.name LIKE '%$search_term%' OR p.article LIKE '%$search_term%' OR p.alternate_name LIKE '%$search_term%')";
}
$gsa_zero_stock_scope_serial = '';
if ($adv_serial === 'yes') {
    $gsa_zero_stock_scope_serial = " AND pc.barcode IS NOT NULL AND TRIM(pc.barcode) != ''";
} elseif ($adv_serial === 'no') {
    $gsa_zero_stock_scope_serial = " AND (pc.barcode IS NULL OR TRIM(pc.barcode) = '')";
}
$gsa_zero_stock_scope_products = '';
if (!empty($adv_product_ids)) {
    $gsa_zero_stock_scope_products = ' AND p.id IN (' . implode(',', array_map('intval', $adv_product_ids)) . ')';
}
$gsa_zero_stock_join_location = '';
$gsa_zero_loc_sql = "MAX('') AS location_name";
if ($gsa_has_loc_column && $gsa_has_loc_table) {
    $gsa_zero_stock_join_location = "\n    LEFT JOIN tbl_location loc_pc ON loc_pc.id = pc.location_id";
    $gsa_zero_loc_sql = 'MAX(loc_pc.name) AS location_name';
}
$gsa_zero_stock_inner = "
    SELECT
        p.id AS product_id,
        pc.id AS product_characteristic_id,
        pbs.branch_id,
        pc.metal_id,
        MAX(p.name) AS product_name,
        MAX(p.article) AS article,
        MAX(p.alternate_name) AS alternate_name,
        MAX(m.display_name) AS metal_name,
        MAX(b.name) AS branch_name,
        $gsa_zero_loc_sql,
        MAX(pc.hsn) AS hsn,
        MAX(pc.sku_code) AS sku_code,
        MAX(pc.making_on) AS making_on,
        MAX(pc.diamond_category) AS diamond_category,
        MAX(pc.carat) AS carat,
        0 AS purchase_qty,
        0 AS purchase_metal_weight,
        0 AS production_qty,
        0 AS production_weight,
        0 AS sale_invoice_qty,
        0 AS inward_gross_sum,
        0 AS inward_pure_sum,
        0 AS outward_pure_sum,
        0 AS available_qty,
        0 AS stock_net_weight,
        0 AS outward_weight_sum,
        0 AS inward_receipt_qty_sum,
        0 AS inward_receipt_weight_sum,
        0 AS inward_receipt_pure_sum,
        0 AS outward_qty_sum,
        0 AS opening_weight,
        MAX(pc.opening_purity) AS opening_purity,
        0 AS sd_gross_opening,
        0 AS sd_gross_in,
        0 AS sd_gross_out,
        0 AS sd_pure_opening,
        0 AS sd_pure_in,
        0 AS sd_pure_out,
        0 AS sd_pcs_opening,
        0 AS sd_pcs_in,
        0 AS sd_pcs_out,
        0 AS value,
        MAX(pc.final_weight) AS final_weight,
        MAX(pc.rate) AS rate
    FROM tbl_product_branch_settings pbs
    INNER JOIN tbl_products p ON p.id = pbs.product_id AND p.status = 1
    INNER JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.branch_id = pbs.branch_id AND pc.status = 1
    INNER JOIN tbl_metal m ON m.id = pc.metal_id
    INNER JOIN tbl_branches b ON b.id = pbs.branch_id
    $gsa_zero_stock_join_location
    WHERE pbs.is_stock_item = 1
    $gsa_zero_stock_scope_branch
    $gsa_zero_stock_scope_metal
    $gsa_zero_stock_scope_search
    $gsa_zero_stock_scope_serial
    $gsa_zero_stock_scope_products
    AND NOT EXISTS (
        SELECT 1 FROM tbl_stock sz
        WHERE sz.product_id = p.id
        AND sz.branch_id = pbs.branch_id
        AND sz.metal_id = pc.metal_id
        AND sz.status = 1
        AND sz.stock_type IN ('opening', 'purchase', 'stock_journal', 'outward', 'balance', 'inward', 'sale_return')
    )
    GROUP BY p.id, pbs.branch_id, pc.metal_id, pc.id
";
$stock_inner_sql = '(' . $stock_inner_sql . ') UNION ALL (' . $gsa_zero_stock_inner . ')';

// Roll characteristic-level rows up to product + branch + metal (matches list/report grain).
$stock_roll_up_sql = "
    SELECT 
        product_id,
        branch_id,
        metal_id,
        MAX(product_characteristic_id) as product_characteristic_id,
        MAX(product_name) as product_name,
        MAX(article) as article,
        MAX(alternate_name) as alternate_name,
        MAX(metal_name) as metal_name,
        MAX(branch_name) as branch_name,
        MAX(location_name) as location_name,
        MAX(hsn) as hsn,
        MAX(sku_code) as sku_code,
        MAX(making_on) as making_on,
        MAX(diamond_category) as diamond_category,
        MAX(carat) as carat,
        SUM(purchase_qty) as purchase_qty,
        MAX(purchase_metal_weight) as purchase_metal_weight,
        MAX(production_qty) as production_qty,
        MAX(production_weight) as production_weight,
        MAX(sale_invoice_qty) as sale_invoice_qty,
        SUM(inward_gross_sum) as inward_gross_sum,
        SUM(inward_pure_sum) as inward_pure_sum,
        SUM(outward_pure_sum) as outward_pure_sum,
        SUM(available_qty) as available_qty,
        SUM(stock_net_weight) as stock_net_weight,
        SUM(outward_weight_sum) as outward_weight_sum,
        SUM(inward_receipt_qty_sum) as inward_receipt_qty_sum,
        SUM(inward_receipt_weight_sum) as inward_receipt_weight_sum,
        SUM(inward_receipt_pure_sum) as inward_receipt_pure_sum,
        SUM(outward_qty_sum) as outward_qty_sum,
        SUM(sd_gross_opening) AS sd_gross_opening,
        SUM(sd_gross_in) AS sd_gross_in,
        SUM(sd_gross_out) AS sd_gross_out,
        SUM(sd_pure_opening) AS sd_pure_opening,
        SUM(sd_pure_in) AS sd_pure_in,
        SUM(sd_pure_out) AS sd_pure_out,
        SUM(sd_pcs_opening) AS sd_pcs_opening,
        SUM(sd_pcs_in) AS sd_pcs_in,
        SUM(sd_pcs_out) AS sd_pcs_out,
        SUM(opening_weight) as opening_weight,
        MAX(opening_purity) as opening_purity,
        SUM(value) as value,
        MAX(final_weight) as final_weight,
        MAX(rate) as rate
    FROM (
        $stock_inner_sql
    ) tmp
    GROUP BY product_id, branch_id, metal_id
";
