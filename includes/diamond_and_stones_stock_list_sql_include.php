<?php

/**
 * Diamond & Stones stock list — Jewellery / Diamonds / GemStones tabs (no LIMIT).
 * Requires $conn, session; sets $dass_stock_list_sql, $has_stock, $gas_has_journal_images, $dass_tab.
 */

require_once __DIR__ . '/diamond_and_stones_stock_columns.php';
require_once __DIR__ . '/auragold_product_metal_tab_match.php';
require_once __DIR__ . '/dass_purchase_invoice_stock_sql.php';

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

$dass_tab = dass_stock_normalize_tab($_GET['tab'] ?? 'jewellery');
$dass_diamond_category = dass_stock_diamond_category_for_tab($dass_tab);

$dass_adv_branch = isset($_GET['adv_branch']) ? (int) $_GET['adv_branch'] : 0;
$dass_adv_category = isset($_GET['adv_category']) ? (int) $_GET['adv_category'] : 0;
$dass_adv_barcode = isset($_GET['adv_barcode']) ? trim((string) $_GET['adv_barcode']) : '';
$dass_adv_rfid = isset($_GET['adv_rfid']) ? trim((string) $_GET['adv_rfid']) : '';
$dass_adv_product = isset($_GET['adv_product']) ? trim((string) $_GET['adv_product']) : '';

$dass_filter_branches = function_exists('auragold_filter_branches_for_login_scope')
    ? auragold_filter_branches_for_login_scope()
    : getList("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
if (!is_array($dass_filter_branches)) {
    $dass_filter_branches = [];
}
$dass_adv_branch = function_exists('auragold_sanitize_adv_branch_for_login_scope')
    ? auragold_sanitize_adv_branch_for_login_scope($dass_adv_branch)
    : $dass_adv_branch;

$dass_filter_categories = getList("SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC");
if (!is_array($dass_filter_categories)) {
    $dass_filter_categories = [];
}

$dass_adv_filter_count = ($dass_adv_branch > 0 ? 1 : 0)
    + ($dass_adv_category > 0 ? 1 : 0)
    + ($dass_adv_barcode !== '' ? 1 : 0)
    + ($dass_adv_rfid !== '' ? 1 : 0)
    + ($dass_adv_product !== '' ? 1 : 0);

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
if ($dass_adv_branch > 0) {
    $branch_filter = $dass_adv_branch;
}

$scope_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 AND display_name = 'Diamond & Stones' ORDER BY display_name ASC, id ASC");
$scope_metal_ids = array_map('intval', array_column($scope_metals ?: [], 'id'));
if (empty($scope_metal_ids)) {
    $scope_metal_ids = [4];
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
$pc_cat_sql = function_exists('auragold_sql_diamond_stones_stock_category_filter')
    ? auragold_sql_diamond_stones_stock_category_filter($dass_diamond_category)
    : (function_exists('auragold_sql_pc_diamond_category_filter')
        ? auragold_sql_pc_diamond_category_filter($dass_diamond_category)
        : (" AND pc.diamond_category = '" . esc($dass_diamond_category) . "'"));
if ($pc_cat_sql !== '') {
    $inner_where[] = ltrim($pc_cat_sql, ' AND ');
}

if ($dass_adv_category > 0) {
    $inner_where[] = 'EXISTS (SELECT 1 FROM tbl_products p_adv WHERE p_adv.id = s.product_id AND p_adv.status = 1 AND p_adv.category_id = ' . (int) $dass_adv_category . ')';
}
if ($dass_adv_barcode !== '') {
    $bc_like = '%' . esc(str_replace(['%', '_'], ['\\%', '\\_'], $dass_adv_barcode)) . '%';
    $inner_where[] = "(s.barcode IS NOT NULL AND TRIM(s.barcode) <> '' AND s.barcode LIKE '$bc_like')";
}
if ($dass_adv_product !== '') {
    $pn_like = '%' . esc(str_replace(['%', '_'], ['\\%', '\\_'], $dass_adv_product)) . '%';
    $inner_where[] = "(p.name LIKE '$pn_like' OR p.article LIKE '$pn_like' OR pc.sku_code LIKE '$pn_like')";
}
if ($dass_adv_rfid !== '') {
    $rf_like = '%' . esc(str_replace(['%', '_'], ['\\%', '\\_'], $dass_adv_rfid)) . '%';
    $rf_parts = [];
    $stock_rfid_col = '';
    if (function_exists('auragold_tbl_has_column')) {
        if (auragold_tbl_has_column($conn, 'tbl_stock', 'rfid_code')) {
            $stock_rfid_col = 'rfid_code';
        } elseif (auragold_tbl_has_column($conn, 'tbl_stock', 'rfid')) {
            $stock_rfid_col = 'rfid';
        }
    }
    if ($stock_rfid_col !== '') {
        $rf_parts[] = "(s.`$stock_rfid_col` IS NOT NULL AND TRIM(s.`$stock_rfid_col`) <> '' AND s.`$stock_rfid_col` LIKE '$rf_like')";
    }
    if ($has_stock_journal && gas_sj_has($sj_cols, 'rfid_code')) {
        $rf_parts[] = "EXISTS (
            SELECT 1 FROM tbl_stock_journal sj_advrf
            WHERE sj_advrf.barcode IS NOT NULL AND TRIM(sj_advrf.barcode) <> ''
              AND (sj_advrf.barcode COLLATE utf8mb4_general_ci) = (s.barcode COLLATE utf8mb4_general_ci)
              AND sj_advrf.rfid_code IS NOT NULL AND TRIM(sj_advrf.rfid_code) <> ''
              AND sj_advrf.rfid_code LIKE '$rf_like'
        )";
    }
    if ($rf_parts !== []) {
        $inner_where[] = '(' . implode(' OR ', $rf_parts) . ')';
    }
}

$inner_sql = implode(' AND ', $inner_where);

$gas_stk_in_types_sql = "'opening','purchase','stock_journal','balance','sale_return','inward'";

$agg_subquery = "
    SELECT s.barcode, s.branch_id,
        (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)
         - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)) AS bal_qty,
        (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)
         - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)) AS bal_wt,
        COALESCE(
            MAX(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql)
                AND COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) > 0.00001 THEN s.id END),
            MAX(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN s.id END),
            MAX(s.id)
        ) AS pick_id
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
    $sj_field_list = [
        ['id', 'sj_row_id'],
        ['barcode', 'sj_barcode'],
        ['invoice_id', 'sj_invoice_id'],
        ['huid_no', 'huid_no'],
        ['voucher_type', 'voucher_type'],
        ['invoice_no', 'invoice_no'],
        ['location', 'sj_location'],
        ['gross_weight', 'gross_weight'],
        ['less_weight', 'less_weight'],
        ['net_weight', 'net_weight'],
        ['purity_weight', 'purity_weight'],
        ['pure_weight', 'pure_weight'],
        ['quantity', 'sj_quantity'],
        ['karat', 'sj_karat'],
        ['rate', 'sj_rate'],
        ['amount', 'sj_amount'],
        ['metal_cost', 'metal_cost'],
        ['making_cost', 'making_cost'],
        ['stone_weight', 'stone_weight'],
        ['making_amount', 'making_amount'],
        ['stone_cost', 'stone_cost'],
        ['purchase_amount', 'purchase_amount'],
        ['sale_amount', 'sale_amount'],
        ['making_type', 'making_type'],
        ['metal_value', 'metal_value'],
        ['stone_rate', 'stone_rate'],
        ['stone_charge_type', 'stone_charge_type'],
        ['stone_amount', 'stone_amount'],
        ['making_rate', 'making_rate'],
        ['wastage_wt', 'wastage_wt'],
        ['wastage_per', 'wastage_per'],
        ['comment', 'sj_comment'],
        ['other_info', 'other_info'],
        ['group_name', 'group_name'],
        ['category', 'sj_category'],
        ['calculation', 'sj_calculation'],
        ['code', 'sj_item_code'],
        ['rfid_code', 'rfid_code'],
        ['design_no', 'design_no'],
        ['minimum_price', 'minimum_price'],
        ['tax_amount', 'tax_amount'],
        ['net_amount', 'net_amount'],
        ['net_amt_with_tax', 'net_amt_with_tax'],
        ['other_amount', 'other_amount'],
        ['setting_charge', 'setting_charge'],
        ['gold_loss_1', 'gold_loss_1'],
        ['gold_loss_2', 'gold_loss_2'],
        ['diamond_amount', 'diamond_amount'],
        ['hallmark_amount', 'hallmark_amount'],
        ['discount_per', 'discount_per'],
        ['created_at', 'sj_created_at'],
        ['status', 'sj_status'],
    ];
    $sj_parts = [];
    foreach ($sj_field_list as $pair) {
        $sj_parts[] = gas_sj_sel_expr($sj_cols, $pair[0], $pair[1]);
    }
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

$pi_join_sql = '';
$dass_pi_select_overrides = [];
$dass_pi_has_loc_join = false;
if (dass_pi_items_table_exists($conn)) {
    $dass_pi_has_loc_join = dass_pi_tbl_exists($conn, 'tbl_locations');
    $pi_join_sql = dass_pi_stock_join_sql($conn, $dass_tab);
    $dass_pi_select_overrides = dass_pi_stock_select_overrides($dass_tab, $dass_pi_has_loc_join);
}

$dass_sql_expr = static function (string $alias, string $default_expr) use ($dass_pi_select_overrides): string {
    if (isset($dass_pi_select_overrides[$alias])) {
        return $dass_pi_select_overrides[$alias] . ' AS `' . str_replace('`', '', $alias) . '`';
    }
    return $default_expr;
};

$img_select_expr = ($img_join_sql !== '') ? 'COALESCE(imgs.image_urls_agg, \'\')' : '\'\'';
$p_desc = gas_tbl_exists($conn, 'tbl_products') && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_products', 'description')
    ? 'COALESCE(NULLIF(TRIM(p.description),\'\'), \'\')'
    : '\'\'';

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
        " . $dass_sql_expr('product_name', 'p.name AS product_name') . ",
        p.article AS article,
        p.id AS product_id,
        $p_desc AS product_description,
        " . $dass_sql_expr('pc_carat', 'pc.carat AS pc_carat') . ",
        pc.diamond_category AS pc_diamond_category,
        pc.sku_code AS pc_sku_code,
        pc.cut AS pc_cut,
        pc.shape AS pc_shape,
        pc.color AS pc_color,
        pc.clarity AS pc_clarity,
        pc.sieve AS pc_sieve,
        pc.size AS pc_size,
        pc.style_code AS pc_style_code,
        " . ($cat_join !== '' ? "COALESCE(NULLIF(TRIM(cat.name),''), NULLIF(TRIM(sj.sj_category),''), '')" : "COALESCE(NULLIF(TRIM(sj.sj_category),''), '')") . " AS category_display,
        " . $dass_sql_expr('supplier_name', ($pi_join !== '' ? 'pi.supplier_name' : "''") . ' AS supplier_name') . ",
        $img_select_expr AS image_urls,
        TRIM(CONCAT_WS(' | ',
            NULLIF(TRIM(sj.sj_comment),''),
            NULLIF(TRIM(sj.other_info),''),
            NULLIF(TRIM(sj.group_name),'')
        )) AS info_text,
        " . $dass_sql_expr('huid_no', 'sj.huid_no AS huid_no') . ",
        sj.voucher_type,
        " . $dass_sql_expr('invoice_no', 'sj.invoice_no AS invoice_no') . ",
        " . $dass_sql_expr('sj_location', 'sj.sj_location AS sj_location') . ",
        sj.rfid_code,
        sj.sj_item_code,
        " . $dass_sql_expr('sj_calculation', 'sj.sj_calculation AS sj_calculation') . ",
        " . $dass_sql_expr('design_no', 'sj.design_no AS design_no') . ",
        " . $dass_sql_expr('sj_gross_weight', 'sj.gross_weight AS sj_gross_weight') . ",
        " . $dass_sql_expr('sj_less_weight', 'sj.less_weight AS sj_less_weight') . ",
        " . $dass_sql_expr('sj_net_weight', 'sj.net_weight AS sj_net_weight') . ",
        " . $dass_sql_expr('sj_purity_weight', 'sj.purity_weight AS sj_purity_weight') . ",
        " . $dass_sql_expr('sj_pure_weight', 'sj.pure_weight AS sj_pure_weight') . ",
        " . $dass_sql_expr('sj_quantity', 'sj.sj_quantity AS sj_quantity') . ",
        " . $dass_sql_expr('sj_karat', 'sj.sj_karat AS sj_karat') . ",
        " . $dass_sql_expr('sj_rate', 'sj.sj_rate AS sj_rate') . ",
        sj.sj_amount,
        sj.metal_cost,
        sj.making_cost,
        " . $dass_sql_expr('stone_weight', 'sj.stone_weight AS stone_weight') . ",
        " . $dass_sql_expr('making_amount', 'sj.making_amount AS making_amount') . ",
        sj.stone_cost,
        " . $dass_sql_expr('purchase_amount', 'sj.purchase_amount AS purchase_amount') . ",
        " . $dass_sql_expr('sale_amount', 'sj.sale_amount AS sale_amount') . ",
        sj.making_type,
        " . $dass_sql_expr('metal_value', 'sj.metal_value AS metal_value') . ",
        sj.stone_rate,
        sj.stone_charge_type,
        " . $dass_sql_expr('stone_amount', 'sj.stone_amount AS stone_amount') . ",
        " . $dass_sql_expr('making_rate', 'sj.making_rate AS making_rate') . ",
        " . $dass_sql_expr('wastage_wt', 'sj.wastage_wt AS wastage_wt') . ",
        sj.wastage_per,
        " . $dass_sql_expr('minimum_price', 'sj.minimum_price AS minimum_price') . ",
        " . $dass_sql_expr('tax_amount', 'sj.tax_amount AS tax_amount') . ",
        " . $dass_sql_expr('net_amount', 'sj.net_amount AS net_amount') . ",
        " . $dass_sql_expr('net_amt_with_tax', 'sj.net_amt_with_tax AS net_amt_with_tax') . ",
        " . $dass_sql_expr('other_amount', 'sj.other_amount AS other_amount') . ",
        " . $dass_sql_expr('setting_charge', 'sj.setting_charge AS setting_charge') . ",
        " . $dass_sql_expr('gold_loss_1', 'sj.gold_loss_1 AS gold_loss_1') . ",
        " . $dass_sql_expr('gold_loss_2', 'sj.gold_loss_2 AS gold_loss_2') . ",
        " . $dass_sql_expr('diamond_amount', 'sj.diamond_amount AS diamond_amount') . ",
        " . $dass_sql_expr('hallmark_amount', 'sj.hallmark_amount AS hallmark_amount') . ",
        " . $dass_sql_expr('discount_per', 'sj.discount_per AS discount_per') . ",
        sj.sj_created_at,
        sj.sj_status,
        " . $dass_sql_expr('metal_rate_disp', 'sj.sj_rate AS metal_rate_disp') . "
";

$from_joins = "
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $sj_join_sql
    $pi_join
    $pi_join_sql
    $img_join_sql
";

if (!$has_stock_journal) {
    $dass_stock_list_sql = "
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
        '' AS product_description,
        pc.carat AS pc_carat,
        pc.diamond_category AS pc_diamond_category,
        pc.sku_code AS pc_sku_code,
        pc.cut AS pc_cut,
        pc.shape AS pc_shape,
        pc.color AS pc_color,
        pc.clarity AS pc_clarity,
        pc.sieve AS pc_sieve,
        pc.size AS pc_size,
        pc.style_code AS pc_style_code,
        '' AS category_display,
        '' AS supplier_name,
        $img_select_expr AS image_urls,
        '' AS info_text,
        NULL AS huid_no, NULL AS voucher_type, NULL AS invoice_no, NULL AS sj_location,
        NULL AS rfid_code, NULL AS sj_item_code, NULL AS sj_calculation, NULL AS design_no,
        NULL AS sj_gross_weight, NULL AS sj_less_weight, NULL AS sj_net_weight,
        NULL AS sj_purity_weight, NULL AS sj_pure_weight, NULL AS sj_quantity, NULL AS sj_karat,
        NULL AS sj_rate, NULL AS sj_amount, NULL AS metal_cost, NULL AS making_cost,
        NULL AS stone_weight, NULL AS making_amount, NULL AS stone_cost, NULL AS purchase_amount,
        NULL AS sale_amount, NULL AS making_type, NULL AS metal_value, NULL AS stone_rate,
        NULL AS stone_charge_type, NULL AS stone_amount, NULL AS making_rate,
        NULL AS wastage_wt, NULL AS wastage_per, NULL AS minimum_price, NULL AS tax_amount,
        NULL AS net_amount, NULL AS net_amt_with_tax, NULL AS other_amount, NULL AS setting_charge,
        NULL AS gold_loss_1, NULL AS gold_loss_2, NULL AS diamond_amount, NULL AS hallmark_amount,
        NULL AS discount_per, NULL AS sj_created_at, NULL AS sj_status
    $from_joins
    ORDER BY s.barcode ASC, s.branch_id ASC
    ";
} else {
    $dass_stock_list_sql = "
    SELECT $select_outer
    $from_joins
    ORDER BY s.barcode ASC, s.branch_id ASC
    ";
}
