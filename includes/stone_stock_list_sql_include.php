<?php

/**
 * Gemstone / coloured-stone stock list — same closing-stock rules as diamond list, filtered by product/category keywords.
 * Requires active mysqli $conn (config.php) and session for branch scope.
 */

if (!function_exists('gas_tbl_exists')) {
    function gas_tbl_exists($conn, $table) {
        $t = mysqli_real_escape_string($conn, $table);
        $r = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }

    /** @return array<string, array> */
    function gas_sj_columns($conn) {
        $cols = [];
        $r = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_stock_journal');
        if ($r) {
            while ($row = mysqli_fetch_assoc($r)) {
                $f = strtolower((string) ($row['Field'] ?? ''));
                if ($f !== '') {
                    $cols[$f] = $row;
                }
            }
            mysqli_free_result($r);
        }
        return $cols;
    }

    function gas_sj_has(array $sj_cols, $name) {
        return isset($sj_cols[strtolower((string) $name)]);
    }

    function gas_sj_active_sql(array $sj_cols) {
        if (!gas_sj_has($sj_cols, 'status')) {
            return '1=1';
        }
        $type = strtolower((string) ($sj_cols['status']['Type'] ?? ''));
        if (strpos($type, 'char') !== false || strpos($type, 'text') !== false || strpos($type, 'enum') !== false) {
            return "(status = 'active' OR LOWER(TRIM(CAST(status AS CHAR))) = 'active')";
        }
        return '(status = 1 OR status = \'1\')';
    }

    function gas_sj_sel_expr(array $sj_cols, $field, $alias = null) {
        $a = $alias !== null ? $alias : $field;
        if (gas_sj_has($sj_cols, $field)) {
            return 'sj1.`' . str_replace('`', '', $field) . '` AS `' . str_replace('`', '', $a) . '`';
        }
        return 'NULL AS `' . str_replace('`', '', $a) . '`';
    }

    /**
     * Match closing-stock branch rules: main branch rows may be stored as NULL/0 branch_id.
     *
     * @return string SQL predicate (no leading AND) or empty when no filter
     */
    function gas_tbl_stock_branch_predicate(mysqli $conn, int $bid, string $alias = 's') {
        if ($bid <= 0) {
            return '';
        }
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_stock', 'branch_id')) {
            return '';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $alias)) {
            $alias = 's';
        }
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        $bid = (int) $bid;
        if ($main > 0 && $bid === $main) {
            return '(' . $alias . '.branch_id = ' . $bid . ' OR ' . $alias . '.branch_id IS NULL OR ' . $alias . '.branch_id = 0)';
        }
        return 'COALESCE(' . $alias . '.branch_id, 0) = ' . $bid;
    }
}

$tab = 'main';
$branch_filter = 0;
$eff_branch = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
if ($eff_branch > 0) {
    $branch_filter = $eff_branch;
} else {
    $wb = isset($_SESSION['working_branch_id']) ? (int) $_SESSION['working_branch_id'] : 0;
    if ($wb <= 0 && !empty($_SESSION['branch_id'])) {
        $wb = (int) $_SESSION['branch_id'];
    }
    if ($wb > 0) {
        $branch_filter = $wb;
    }
}

$scope_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 AND display_name = 'Diamond & Stones' ORDER BY display_name ASC, id ASC");
$scope_metal_ids = array_map('intval', array_column($scope_metals ?: [], 'id'));
if (empty($scope_metal_ids)) {
    $scope_metal_ids = [4];
}
if (empty($scope_metals) && $scope_metal_ids !== []) {
    $ids_esc = implode(',', array_map('intval', $scope_metal_ids));
    $scope_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 AND id IN ($ids_esc) ORDER BY display_name ASC, id ASC");
}
$tab_metal_ids = array_values(array_unique(array_map('intval', $scope_metal_ids)));
$scope_ids_sql = implode(',', $tab_metal_ids);
if ($scope_ids_sql === '') {
    $scope_ids_sql = '0';
}

$has_stock = gas_tbl_exists($conn, 'tbl_stock');
$sj_cols = [];
$has_stock_journal = gas_tbl_exists($conn, 'tbl_stock_journal');
if ($has_stock_journal) {
    $sj_cols = gas_sj_columns($conn);
    if (!gas_sj_has($sj_cols, 'id') || !gas_sj_has($sj_cols, 'barcode')) {
        $has_stock_journal = false;
    }
}

$metal_has_display = false;
$metal_has_system = false;
$mh = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_metal');
if ($mh) {
    while ($mr = mysqli_fetch_assoc($mh)) {
        $fn = strtolower((string) ($mr['Field'] ?? ''));
        if ($fn === 'display_name') {
            $metal_has_display = true;
        }
        if ($fn === 'system_name') {
            $metal_has_system = true;
        }
    }
    mysqli_free_result($mh);
}
if ($metal_has_display && $metal_has_system) {
    $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.display_name), \'\'), NULLIF(TRIM(m.system_name), \'\'), \'\')';
} elseif ($metal_has_display) {
    $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.display_name), \'\'), \'\')';
} elseif ($metal_has_system) {
    $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.system_name), \'\'), \'\')';
} else {
    $metal_name_expr = '\'\'';
}

$inner_where = [
    's.status = 1',
    "(s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,'')) <> '')",
    's.metal_id IN (' . $scope_ids_sql . ')',
];
$gas_br_pred = gas_tbl_stock_branch_predicate($conn, $branch_filter, 's');
if ($gas_br_pred !== '') {
    $inner_where[] = $gas_br_pred;
}
// Exclude obvious diamond-only SKUs; include gemstone / stone keywords (adjust if your category names differ).
$inner_where[] = "(EXISTS (
    SELECT 1 FROM tbl_products p_st
    LEFT JOIN tbl_categories c_st ON c_st.id = p_st.category_id
    WHERE p_st.id = s.product_id AND p_st.id IS NOT NULL AND p_st.id > 0
    AND (
        LOWER(CONCAT(' ', COALESCE(c_st.name,''), ' ', COALESCE(p_st.name,''))) REGEXP 'ruby|emerald|sapphire|pearl|opal|beads|topaz|amethyst|citrine|tanzanite|spinel|tourmaline|garnet|onyx|cabochon|gemstone|gem stone|semi precious|semi-precious|coloured stone|colored stone'
        OR (LOWER(TRIM(COALESCE(c_st.name,''))) LIKE '%stone%' AND LOWER(TRIM(COALESCE(c_st.name,''))) NOT LIKE '%diamond%')
    )
    AND LOWER(TRIM(COALESCE(c_st.name,''))) NOT LIKE '%diamond%'
))";
$inner_sql = implode(' AND ', $inner_where);

$gas_stk_in_types_sql = "'opening','purchase','stock_journal','balance','sale_return'";

$agg_subquery = "
    SELECT s.barcode, s.branch_id,
        (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)
         - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)) AS bal_qty,
        (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)
         - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)) AS bal_wt,
        COALESCE(MAX(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN s.id END), MAX(s.id)) AS pick_id
    FROM tbl_stock s
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    WHERE $inner_sql
    GROUP BY s.barcode, s.branch_id
    HAVING (bal_qty > 0.00001 OR bal_wt > 0.00001)
";

$sj_join_sql = '';
$img_join_sql = '';
if ($has_stock_journal) {
    $sj_status_sql = gas_sj_active_sql($sj_cols);
    $sj_parts = [
        gas_sj_sel_expr($sj_cols, 'id', 'sj_row_id'),
        gas_sj_sel_expr($sj_cols, 'barcode', 'sj_barcode'),
        gas_sj_sel_expr($sj_cols, 'invoice_id', 'sj_invoice_id'),
        gas_sj_sel_expr($sj_cols, 'huid_no', 'huid_no'),
        gas_sj_sel_expr($sj_cols, 'voucher_type', 'voucher_type'),
        gas_sj_sel_expr($sj_cols, 'invoice_no', 'invoice_no'),
        gas_sj_sel_expr($sj_cols, 'location', 'sj_location'),
        gas_sj_sel_expr($sj_cols, 'gross_weight', 'gross_weight'),
        gas_sj_sel_expr($sj_cols, 'net_weight', 'net_weight'),
        gas_sj_sel_expr($sj_cols, 'purity_weight', 'purity_weight'),
        gas_sj_sel_expr($sj_cols, 'pure_weight', 'pure_weight'),
        gas_sj_sel_expr($sj_cols, 'quantity', 'sj_quantity'),
        gas_sj_sel_expr($sj_cols, 'karat', 'sj_karat'),
        gas_sj_sel_expr($sj_cols, 'metal_cost', 'metal_cost'),
        gas_sj_sel_expr($sj_cols, 'making_cost', 'making_cost'),
        gas_sj_sel_expr($sj_cols, 'stone_weight', 'stone_weight'),
        gas_sj_sel_expr($sj_cols, 'making_amount', 'making_amount'),
        gas_sj_sel_expr($sj_cols, 'stone_cost', 'stone_cost'),
        gas_sj_sel_expr($sj_cols, 'purchase_amount', 'purchase_amount'),
        gas_sj_sel_expr($sj_cols, 'making_type', 'making_type'),
        gas_sj_sel_expr($sj_cols, 'metal_value', 'metal_value'),
        gas_sj_sel_expr($sj_cols, 'stone_rate', 'stone_rate'),
        gas_sj_sel_expr($sj_cols, 'stone_charge_type', 'stone_charge_type'),
        gas_sj_sel_expr($sj_cols, 'stone_amount', 'stone_amount'),
        gas_sj_sel_expr($sj_cols, 'making_rate', 'making_rate'),
        gas_sj_sel_expr($sj_cols, 'wastage_wt', 'wastage_wt'),
        gas_sj_sel_expr($sj_cols, 'wastage_per', 'wastage_per'),
        gas_sj_sel_expr($sj_cols, 'comment', 'sj_comment'),
        gas_sj_sel_expr($sj_cols, 'other_info', 'other_info'),
        gas_sj_sel_expr($sj_cols, 'group_name', 'group_name'),
        gas_sj_sel_expr($sj_cols, 'category', 'sj_category'),
        gas_sj_sel_expr($sj_cols, 'created_at', 'sj_created_at'),
        gas_sj_sel_expr($sj_cols, 'status', 'sj_status'),
    ];
    $sj_sel_sql = implode(",\n            ", $sj_parts);
    $sj_join_sql = "
    LEFT JOIN (
        SELECT
            $sj_sel_sql
        FROM tbl_stock_journal sj1
        INNER JOIN (
            SELECT barcode, MAX(id) AS max_id
            FROM tbl_stock_journal
            WHERE ($sj_status_sql) AND barcode IS NOT NULL AND TRIM(barcode) <> ''
            GROUP BY barcode
        ) sjmx ON sjmx.max_id = sj1.id
    ) sj ON (sj.sj_barcode COLLATE utf8mb4_general_ci) = (s.barcode COLLATE utf8mb4_general_ci)
        AND s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,'')) <> ''
    ";
}

$gas_has_journal_images = gas_tbl_exists($conn, 'tbl_stock_journal_images');
if ($gas_has_journal_images) {
    $img_join_sql = "
    LEFT JOIN (
        SELECT barcode_no, GROUP_CONCAT(image_path ORDER BY id SEPARATOR ',') AS image_urls_agg
        FROM tbl_stock_journal_images
        GROUP BY barcode_no
    ) imgs ON TRIM(imgs.barcode_no) = TRIM(s.barcode)
    ";
}

$pi_join = '';
if (gas_tbl_exists($conn, 'tbl_purchase_invoices')) {
    $pi_join = 'LEFT JOIN tbl_purchase_invoices pi ON pi.id = sj.sj_invoice_id';
}

$cat_join = '';
if (gas_tbl_exists($conn, 'tbl_categories')) {
    $cat_join = 'LEFT JOIN tbl_categories cat ON cat.id = p.category_id';
}

$img_select_expr = ($img_join_sql !== '') ? 'COALESCE(imgs.image_urls_agg, \'\')' : '\'\'';

$select_outer = "
        s.id AS stock_id,
        s.barcode,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_purity,
        s.status AS stock_status,
        s.created_at AS stock_created_at,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.carat AS pc_carat,
        " . ($cat_join !== '' ? "COALESCE(NULLIF(TRIM(cat.name),''), NULLIF(TRIM(sj.sj_category),''), '')" : "COALESCE(NULLIF(TRIM(sj.sj_category),''), '')") . " AS category_display,
        " . ($pi_join !== '' ? 'pi.supplier_name' : "''") . " AS supplier_name,
        $img_select_expr AS image_urls,
        TRIM(CONCAT_WS(' | ',
            NULLIF(TRIM(sj.sj_comment),''),
            NULLIF(TRIM(sj.other_info),''),
            NULLIF(TRIM(sj.group_name),'')
        )) AS info_text,
        sj.huid_no,
        sj.voucher_type,
        sj.invoice_no,
        sj.sj_location,
        sj.gross_weight AS sj_gross_weight,
        sj.net_weight AS sj_net_weight,
        sj.purity_weight AS sj_purity_weight,
        sj.pure_weight AS sj_pure_weight,
        sj.sj_quantity,
        sj.sj_karat,
        sj.metal_cost,
        sj.making_cost,
        sj.stone_weight,
        sj.making_amount,
        sj.stone_cost,
        sj.purchase_amount,
        sj.making_type,
        sj.metal_value,
        sj.stone_rate,
        sj.stone_charge_type,
        sj.stone_amount,
        sj.making_rate,
        sj.wastage_wt,
        sj.wastage_per,
        sj.sj_created_at,
        sj.sj_status
";

if (!$has_stock_journal) {
    $stone_stock_list_sql = "
    SELECT
        s.id AS stock_id,
        s.barcode,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_purity,
        s.status AS stock_status,
        s.created_at AS stock_created_at,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.carat AS pc_carat,
        '' AS category_display,
        '' AS supplier_name,
        $img_select_expr AS image_urls,
        '' AS info_text,
        NULL AS huid_no,
        NULL AS voucher_type,
        NULL AS invoice_no,
        NULL AS sj_location,
        NULL AS sj_gross_weight,
        NULL AS sj_net_weight,
        NULL AS sj_purity_weight,
        NULL AS sj_pure_weight,
        NULL AS sj_quantity,
        NULL AS sj_karat,
        NULL AS metal_cost,
        NULL AS making_cost,
        NULL AS stone_weight,
        NULL AS making_amount,
        NULL AS stone_cost,
        NULL AS purchase_amount,
        NULL AS making_type,
        NULL AS metal_value,
        NULL AS stone_rate,
        NULL AS stone_charge_type,
        NULL AS stone_amount,
        NULL AS making_rate,
        NULL AS wastage_wt,
        NULL AS wastage_per,
        NULL AS sj_created_at,
        NULL AS sj_status
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $img_join_sql
    ORDER BY s.barcode ASC, s.branch_id ASC
    ";
} else {
    $stone_stock_list_sql = "
    SELECT $select_outer
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $sj_join_sql
    $pi_join
    $img_join_sql
    ORDER BY s.barcode ASC, s.branch_id ASC
    ";
}
