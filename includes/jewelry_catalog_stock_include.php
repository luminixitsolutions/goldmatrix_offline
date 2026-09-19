<?php

/**
 * Jewellery catalogue — available barcoded stock across all active metals.
 */
require_once __DIR__ . '/gold_silver_stock_list_fetch.php';

/**
 * @return array{branches: array, metals: array, products: array, categories: array, articles: array, carats: array}
 */
function auragold_jewelry_catalog_filter_masters(mysqli $conn): array
{
    $out = [
        'branches' => [],
        'metals' => auragold_jewelry_catalog_metals($conn),
        'products' => [],
        'categories' => [],
        'articles' => [],
        'carats' => [],
    ];
    if (gas_tbl_exists($conn, 'tbl_branches')) {
        $out['branches'] = getList('SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC') ?: [];
    }
    if (gas_tbl_exists($conn, 'tbl_products')) {
        $out['products'] = getList(
            'SELECT id, name FROM tbl_products WHERE status = 1 ORDER BY name ASC LIMIT 800'
        ) ?: [];
        $out['articles'] = getList(
            "SELECT DISTINCT TRIM(article) AS article FROM tbl_products
             WHERE status = 1 AND article IS NOT NULL AND TRIM(article) <> ''
             ORDER BY article ASC LIMIT 500"
        ) ?: [];
    }
    if (gas_tbl_exists($conn, 'tbl_categories')) {
        $out['categories'] = getList(
            'SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC LIMIT 300'
        ) ?: [];
    }
    if (gas_tbl_exists($conn, 'tbl_carat')) {
        $carat_name_col = 'name';
        if (function_exists('auragold_tbl_has_column') && !auragold_tbl_has_column($conn, 'tbl_carat', 'name')
            && auragold_tbl_has_column($conn, 'tbl_carat', 'carat')) {
            $carat_name_col = 'carat';
        }
        $out['carats'] = getList(
            'SELECT id, `' . str_replace('`', '', $carat_name_col) . '` AS name FROM tbl_carat WHERE status = 1 ORDER BY `'
            . str_replace('`', '', $carat_name_col) . '` ASC LIMIT 100'
        ) ?: [];
    } elseif (gas_tbl_exists($conn, 'tbl_product_characteristics')) {
        $out['carats'] = getList(
            'SELECT DISTINCT TRIM(carat) AS name FROM tbl_product_characteristics '
            . 'WHERE carat IS NOT NULL AND TRIM(carat) <> \'\' ORDER BY carat ASC LIMIT 100'
        ) ?: [];
    }

    return $out;
}

/**
 * @return array<int, array{id:int,name:string}>
 */
function auragold_jewelry_catalog_metals(mysqli $conn): array
{
    if (!gas_tbl_exists($conn, 'tbl_metal')) {
        return [];
    }
    $rows = getList(
        "SELECT id,
            COALESCE(NULLIF(TRIM(display_name), ''), NULLIF(TRIM(system_name), ''), CONCAT('Metal ', id)) AS name
         FROM tbl_metal
         WHERE status = 1
         ORDER BY id ASC"
    );

    return is_array($rows) ? $rows : [];
}

/**
 * @param array<string, mixed> $opts
 * @return array{rows: array<int, array<string, mixed>>, metals: array, error: string, has_journal_images: bool}
 */
function auragold_jewelry_catalog_stock_fetch(mysqli $conn, array $opts = []): array
{
    $metal_filter = (int) ($opts['metal_id'] ?? 0);
    $branch_filter_id = (int) ($opts['branch_id'] ?? 0);
    $product_filter = (int) ($opts['product_id'] ?? 0);
    $category_filter = (int) ($opts['category_id'] ?? 0);
    $search = mb_strtolower(trim((string) ($opts['q'] ?? '')));
    $article_f = trim((string) ($opts['article'] ?? ''));
    $barcode_f = trim((string) ($opts['barcode'] ?? ''));
    $design_f = trim((string) ($opts['design_no'] ?? ''));
    $location_f = trim((string) ($opts['location'] ?? ''));
    $comment_f = trim((string) ($opts['comment'] ?? ''));
    $gross_wt_f = trim((string) ($opts['gross_wt'] ?? ''));
    $rfid_f = trim((string) ($opts['rfid_code'] ?? ''));
    $limit = (int) ($opts['limit'] ?? 3000);
    if ($limit < 1) {
        $limit = 1;
    }
    if ($limit > 5000) {
        $limit = 5000;
    }

    $metals = auragold_jewelry_catalog_metals($conn);
    $scope_metal_ids = array_values(array_unique(array_map(static function ($m) {
        return (int) ($m['id'] ?? 0);
    }, $metals)));
    $scope_metal_ids = array_values(array_filter($scope_metal_ids, static function ($id) {
        return $id > 0;
    }));
    if ($scope_metal_ids === []) {
        $scope_metal_ids = [1, 2, 3, 4];
    }
    if ($metal_filter > 0) {
        if (!in_array($metal_filter, $scope_metal_ids, true)) {
            return ['rows' => [], 'metals' => $metals, 'error' => '', 'has_journal_images' => false];
        }
        $scope_metal_ids = [$metal_filter];
    }
    $scope_ids_sql = implode(',', $scope_metal_ids);

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

    $has_stock = gas_tbl_exists($conn, 'tbl_stock');
    $has_stock_journal = gas_tbl_exists($conn, 'tbl_stock_journal');
    $sj_cols = [];
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
    if ($branch_filter_id > 0 && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock', 'branch_id')) {
        $inner_where[] = 'COALESCE(s.branch_id, 0) = ' . (int) $branch_filter_id;
    } else {
        $gas_br_pred = gas_tbl_stock_branch_predicate($conn, $branch_filter, 's');
        if ($gas_br_pred !== '') {
            $inner_where[] = $gas_br_pred;
        }
    }
    if ($product_filter > 0) {
        $inner_where[] = 's.product_id = ' . $product_filter;
    }
    if ($category_filter > 0 && gas_tbl_exists($conn, 'tbl_products')) {
        $inner_where[] = 'p.category_id = ' . $category_filter;
    }
    if ($search !== '') {
        $qesc = mysqli_real_escape_string($conn, $search);
        $inner_where[] = "(LOWER(TRIM(s.barcode)) LIKE '%$qesc%'
            OR LOWER(COALESCE(p.name,'')) LIKE '%$qesc%'
            OR LOWER(COALESCE(p.article,'')) LIKE '%$qesc%')";
    }
    if ($article_f !== '') {
        $aesc = mysqli_real_escape_string($conn, $article_f);
        $inner_where[] = "TRIM(COALESCE(p.article,'')) = '$aesc'";
    }
    if ($barcode_f !== '') {
        $besc = mysqli_real_escape_string($conn, $barcode_f);
        $inner_where[] = "TRIM(s.barcode) LIKE '%$besc%'";
    }
    if ($design_f !== '') {
        $desc = mysqli_real_escape_string($conn, $design_f);
        $inner_where[] = "(TRIM(COALESCE(p.article,'')) LIKE '%$desc%' OR TRIM(s.barcode) LIKE '%$desc%')";
    }
    $inner_sql = implode(' AND ', $inner_where);

    $gas_stk_in_types_sql = "'opening','purchase','stock_journal','balance','sale_return'";

    $agg_subquery = "
    SELECT s.barcode, s.branch_id, s.metal_id,
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
    GROUP BY s.barcode, s.branch_id, s.metal_id
    HAVING (bal_qty > 0.00001 OR bal_wt > 0.00001)
";

    $sj_join_sql = '';
    if ($has_stock_journal) {
        $sj_status_sql = gas_sj_active_sql($sj_cols);
        $sj_parts = [
            gas_sj_sel_expr($sj_cols, 'barcode', 'sj_barcode'),
            gas_sj_sel_expr($sj_cols, 'location', 'sj_location'),
            gas_sj_sel_expr($sj_cols, 'gross_weight', 'gross_weight'),
            gas_sj_sel_expr($sj_cols, 'net_weight', 'net_weight'),
            gas_sj_sel_expr($sj_cols, 'karat', 'sj_karat'),
            gas_sj_sel_expr($sj_cols, 'category', 'sj_category'),
            gas_sj_sel_expr($sj_cols, 'purchase_amount', 'purchase_amount'),
            gas_sj_sel_expr($sj_cols, 'metal_value', 'metal_value'),
            gas_sj_sel_expr($sj_cols, 'comment', 'sj_comment'),
            gas_sj_sel_expr($sj_cols, 'other_info', 'other_info'),
            gas_sj_sel_expr($sj_cols, 'group_name', 'group_name'),
            gas_sj_sel_expr($sj_cols, 'status', 'sj_status'),
        ];
        $sj_sel_sql = implode(",\n            ", $sj_parts);
        $sj_join_sql = "
    LEFT JOIN (
        SELECT $sj_sel_sql
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
    $img_join_sql = '';
    if ($gas_has_journal_images) {
        $img_join_sql = "
    LEFT JOIN (
        SELECT barcode_no, GROUP_CONCAT(image_path ORDER BY id SEPARATOR ',') AS image_urls_agg
        FROM tbl_stock_journal_images
        GROUP BY barcode_no
    ) imgs ON TRIM(imgs.barcode_no) = TRIM(s.barcode)
    ";
    }
    $img_select_expr = ($img_join_sql !== '') ? 'COALESCE(imgs.image_urls_agg, \'\')' : '\'\'';

    $cat_join = '';
    if (gas_tbl_exists($conn, 'tbl_categories')) {
        $cat_join = 'LEFT JOIN tbl_categories cat ON cat.id = p.category_id';
    }
    $category_expr = ($cat_join !== '')
        ? "COALESCE(NULLIF(TRIM(cat.name),''), '')"
        : "''";
    if ($has_stock_journal) {
        $category_expr = ($cat_join !== '')
            ? "COALESCE(NULLIF(TRIM(cat.name),''), NULLIF(TRIM(sj.sj_category),''), '')"
            : "COALESCE(NULLIF(TRIM(sj.sj_category),''), '')";
    }

    $sj_location_sel = $has_stock_journal ? 'sj.sj_location' : 'NULL';
    $sj_gross_sel = $has_stock_journal ? 'sj.gross_weight' : 'NULL';
    $sj_purchase_sel = $has_stock_journal ? 'sj.purchase_amount' : 'NULL';
    $sj_metal_val_sel = $has_stock_journal ? 'sj.metal_value' : 'NULL';
    $sj_group_sel = $has_stock_journal ? 'sj.group_name' : 'NULL';
    $sj_karat_sel = $has_stock_journal ? 'sj.sj_karat' : 'NULL';
    $sj_comment_sel = $has_stock_journal ? 'sj.sj_comment' : 'NULL';
    $sj_other_sel = $has_stock_journal ? 'sj.other_info' : 'NULL';
    $sj_status_sel = $has_stock_journal ? 'sj.sj_status' : 'NULL';

    $sql = "
    SELECT
        s.id AS stock_id,
        s.barcode,
        s.metal_id,
        s.status AS stock_status,
        s.branch_id,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.carat AS pc_carat,
        $category_expr AS category_display,
        $img_select_expr AS image_urls,
        $sj_location_sel AS sj_location,
        $sj_gross_sel AS sj_gross_weight,
        $sj_purchase_sel AS purchase_amount,
        $sj_metal_val_sel AS metal_value,
        $sj_group_sel AS group_name,
        $sj_karat_sel AS sj_karat,
        $sj_comment_sel AS sj_comment,
        $sj_other_sel AS other_info,
        $sj_status_sel AS sj_status
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $sj_join_sql
    $img_join_sql
    ORDER BY s.barcode ASC, s.metal_id ASC, s.branch_id ASC
    LIMIT $limit
    ";

    $rows = [];
    $load_error = '';
    if (!$has_stock) {
        $load_error = 'Stock table not found.';
    } else {
        $res = mysqli_query($conn, $sql);
        if (!$res) {
            $load_error = 'Could not load catalogue stock: ' . mysqli_error($conn);
        } else {
            while ($r = mysqli_fetch_assoc($res)) {
                if ($location_f !== '') {
                    $loc = strtolower(trim((string) ($r['sj_location'] ?? '')));
                    if (strpos($loc, strtolower($location_f)) === false) {
                        continue;
                    }
                }
                if ($comment_f !== '') {
                    $cm = strtolower(trim((string) ($r['sj_comment'] ?? '') . ' ' . (string) ($r['other_info'] ?? '')));
                    if (strpos($cm, strtolower($comment_f)) === false) {
                        continue;
                    }
                }
                if ($gross_wt_f !== '' && is_numeric($gross_wt_f)) {
                    $gw = (float) ($r['sj_gross_weight'] ?? 0);
                    if (abs($gw - (float) $gross_wt_f) > 0.001) {
                        continue;
                    }
                }
                if ($rfid_f !== '') {
                    $bc = strtolower(trim((string) ($r['barcode'] ?? '')));
                    if (strpos($bc, strtolower($rfid_f)) === false) {
                        continue;
                    }
                }
                $rows[] = $r;
            }
            mysqli_free_result($res);
        }
    }

    return [
        'rows' => $rows,
        'metals' => $metals,
        'error' => $load_error,
        'has_journal_images' => $gas_has_journal_images,
    ];
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function auragold_jewelry_catalog_normalize_row(array $row, $SiteUrl): array
{
    $site = is_string($SiteUrl) ? $SiteUrl : '';

    $thumb = '';
    $raw_imgs = trim((string) ($row['image_urls'] ?? ''));
    if ($raw_imgs !== '') {
        foreach (explode(',', $raw_imgs) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $thumb = gas_public_url_for_stored_path($part, $site);
            if ($thumb !== '') {
                break;
            }
        }
    }

    $qty = (float) ($row['current_qty'] ?? 0);
    $wt = (float) ($row['current_weight'] ?? 0);
    $carat = trim((string) ($row['pc_carat'] ?? ''));
    if ($carat === '' && !empty($row['sj_karat'])) {
        $carat = trim((string) $row['sj_karat']);
    }
    $article = trim((string) ($row['article'] ?? ''));
    $barcode = trim((string) ($row['barcode'] ?? ''));
    $subtitle_parts = array_filter([$article, $carat], static function ($v) {
        return $v !== '';
    });

    $stk_st = (int) ($row['stock_status'] ?? 1);
    $active = $stk_st === 1 ? 'Active' : 'Inactive';

    $design_base = $article !== '' ? $article : $barcode;
    $qty_disp = gas_fmt_num($qty, 0);
    $design_no = $design_base . ' (' . $qty_disp . ')';

    $variants = $carat;
    if ($variants === '' && trim((string) ($row['category_display'] ?? '')) !== '') {
        $variants = trim((string) $row['category_display']);
    }

    $bom = trim((string) ($row['group_name'] ?? ''));
    if ($bom === '') {
        $bom = trim((string) ($row['other_info'] ?? ''));
    }

    $amount = 0.0;
    if (isset($row['purchase_amount']) && $row['purchase_amount'] !== '' && $row['purchase_amount'] !== null) {
        $amount = (float) $row['purchase_amount'];
    } elseif (isset($row['metal_value']) && $row['metal_value'] !== '' && $row['metal_value'] !== null) {
        $amount = (float) $row['metal_value'];
    }

    $weight_show = $wt;
    if ($weight_show <= 0.00001 && !empty($row['sj_gross_weight'])) {
        $weight_show = (float) $row['sj_gross_weight'];
    }

    return [
        'stock_id' => (int) ($row['stock_id'] ?? 0),
        'barcode' => $barcode,
        'metal_id' => (int) ($row['metal_id'] ?? 0),
        'metal_name' => trim((string) ($row['metal_name'] ?? '')),
        'product_name' => trim((string) ($row['product_name'] ?? '')),
        'article' => $article,
        'carat' => $carat,
        'category' => trim((string) ($row['category_display'] ?? '')),
        'branch_name' => trim((string) ($row['branch_name'] ?? '')),
        'current_qty' => $qty,
        'current_weight' => $wt,
        'qty_label' => $qty_disp,
        'weight_label' => gas_fmt_num($weight_show, 3),
        'thumb_url' => $thumb,
        'image_urls' => $raw_imgs,
        'subtitle' => implode(' | ', $subtitle_parts),
        'title' => trim((string) ($row['product_name'] ?? '')) !== ''
            ? trim((string) $row['product_name']) . ' — ' . trim((string) ($row['metal_name'] ?? 'Metal'))
            : trim((string) ($row['metal_name'] ?? 'Stock')),
        'active' => $active,
        'jewelry_catalogue' => 'Yes',
        'design_no' => $design_no,
        'variants' => $variants,
        'bill_of_material' => $bom,
        'amount' => $amount,
        'amount_label' => gas_fmt_money($amount),
        'location' => trim((string) ($row['sj_location'] ?? '')),
    ];
}
