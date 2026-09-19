<?php
/**
 * Sale invoice line rows for Gold/Silver and Diamond/Stone financial analysis pages.
 * Branch: uses tbl_sale_invoices.branch_id when present, else tbl_product_characteristics.branch_id.
 */

if (!function_exists('auragold_financial_analysis_tbl_exists')) {
    function auragold_financial_analysis_tbl_exists(mysqli $conn, string $table): bool {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($t === '') {
            return false;
        }
        $e = mysqli_real_escape_string($conn, $t);
        $r = @mysqli_query($conn, "SHOW TABLES LIKE '$e'");
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }
}

if (!function_exists('auragold_financial_analysis_normalize_scope')) {
    function auragold_financial_analysis_normalize_scope(string $scope): string {
        $scope = strtolower(trim($scope));
        if ($scope === 'diamond_stone' || $scope === 'diamond') {
            return 'diamond_stone';
        }
        if ($scope === 'imitation' || $scope === 'imitation_watches' || $scope === 'watch' || $scope === 'watches') {
            return 'imitation';
        }

        return 'gold_silver';
    }
}

if (!function_exists('auragold_financial_analysis_metal_ids')) {
    /**
     * @return int[]
     */
    function auragold_financial_analysis_metal_ids(mysqli $conn, string $scope): array {
        $scope = function_exists('auragold_financial_analysis_normalize_scope')
            ? auragold_financial_analysis_normalize_scope($scope)
            : ($scope === 'diamond_stone' ? 'diamond_stone' : 'gold_silver');
        if ($scope === 'gold_silver') {
            $list = getList("SELECT id FROM tbl_metal WHERE status = 1 AND display_name IN ('Gold','Silver') ORDER BY id ASC");
        } elseif ($scope === 'imitation') {
            $list = getList("SELECT id FROM tbl_metal WHERE status = 1 AND (
                display_name = 'Imitation Or Watches'
                OR LOWER(TRIM(display_name)) IN ('imitation', 'watch', 'watches', 'imitation or watches')
            ) ORDER BY id ASC");
        } else {
            $list = getList("SELECT id FROM tbl_metal WHERE status = 1 AND display_name = 'Diamond & Stones' ORDER BY id ASC");
        }
        $ids = array_map('intval', array_column($list ?: [], 'id'));
        $ids = array_values(array_filter($ids, static function ($x) {
            return $x > 0;
        }));
        if ($ids === [] && $scope === 'gold_silver') {
            return [1, 2];
        }
        if ($ids === [] && $scope === 'diamond_stone') {
            return [4];
        }
        if ($ids === [] && $scope === 'imitation') {
            return [5];
        }
        return $ids;
    }
}

if (!function_exists('auragold_financial_analysis_gold_silver_metal_predicate')) {
    /**
     * Gold/Silver lines are included when the linked characteristic is Gold/Silver, or when
     * the same product has an active Gold/Silver characteristic on the invoice/line branch —
     * so making-only rows (metal amount 0) still appear when the item is effectively a jewel item.
     */
    function auragold_financial_analysis_gold_silver_metal_predicate(string $si, string $sii, string $pc, string $idList): string {
        return '('
            . "COALESCE({$pc}.metal_id, 0) IN ({$idList}) "
            . 'OR EXISTS ('
            . 'SELECT 1 FROM tbl_product_characteristics gs_pc '
            . "WHERE gs_pc.product_id = {$sii}.product_id "
            . 'AND gs_pc.status = 1 '
            . "AND gs_pc.metal_id IN ({$idList}) "
            . 'AND ('
            . "COALESCE(NULLIF({$si}.branch_id, 0), NULLIF({$pc}.branch_id, 0), 0) = 0 "
            . "OR gs_pc.branch_id = COALESCE(NULLIF({$si}.branch_id, 0), NULLIF({$pc}.branch_id, 0))"
            . ')'
            . ')'
            . ')';
    }
}

if (!function_exists('auragold_financial_analysis_branch_where')) {
    function auragold_financial_analysis_branch_where(mysqli $conn, int $effBranchId, string $siAlias, string $pcAlias, string $invoiceTable = 'tbl_sale_invoices'): string {
        if ($effBranchId <= 0) {
            return '';
        }
        $bid = (int) $effBranchId;
        $inv = preg_replace('/[^a-zA-Z0-9_]/', '', $invoiceTable) ?: 'tbl_sale_invoices';
        $siHas = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $inv, 'branch_id');
        if ($siHas) {
            return " AND (
                ({$siAlias}.branch_id IS NOT NULL AND {$siAlias}.branch_id > 0 AND {$siAlias}.branch_id = {$bid})
                OR (({$siAlias}.branch_id IS NULL OR {$siAlias}.branch_id = 0) AND {$pcAlias}.branch_id = {$bid})
            )";
        }
        return " AND {$pcAlias}.branch_id = {$bid}";
    }
}

if (!function_exists('auragold_financial_analysis_base_select')) {
    function auragold_financial_analysis_base_select(mysqli $conn, string $si, string $sii, string $scope): string {
        $pc = 'pc';
        $pcHasCreated = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_product_characteristics', 'created_at');
        $barcodedExpr = $pcHasCreated
            ? "DATE_FORMAT({$pc}.created_at, '%d-%m-%Y')"
            : "''";

        $caratNum = "CAST(NULLIF(TRIM(REPLACE(IFNULL({$sii}.carat, ''), ',', '')), '') AS DECIMAL(18, 4))";
        $purityExpr = "COALESCE({$sii}.purity, {$pc}.carat, 0)";
        $metalRateExpr = "COALESCE({$sii}.metal_rate, {$sii}.rate, 0)";
        $stoneWExpr = "COALESCE({$sii}.stone_weight, {$sii}.less_weight, 0)";
        $metalValExpr = "COALESCE({$sii}.metal_value, GREATEST(0, COALESCE({$sii}.amount, 0) - COALESCE({$sii}.making_amount, 0) - COALESCE({$sii}.stone_amount, 0) - COALESCE({$sii}.diamond_amount, 0)))";

        $ledgerExpr = "TRIM(CONCAT_WS(' — ', NULLIF(TRIM({$si}.against_of), ''), NULLIF(TRIM({$si}.customer_name), '')))";

        /** Normalized for CASE/WHERE so mixed table collations (e.g. utf8mb4_bin vs _0900_ai_ci) do not error. */
        $diamondCat = "LOWER(TRIM(CONVERT(COALESCE({$sii}.diamond_category, {$pc}.diamond_category, '') USING utf8mb4))) COLLATE utf8mb4_unicode_ci";
        $gemsLiteral = "CONVERT('gemstones' USING utf8mb4) COLLATE utf8mb4_unicode_ci";
        $diamondWtExpr = "CASE WHEN {$diamondCat} = {$gemsLiteral} THEN 0 ELSE COALESCE({$sii}.stone_weight, 0) END";
        $gemWtExpr = "CASE WHEN {$diamondCat} = {$gemsLiteral} THEN COALESCE({$sii}.stone_weight, 0) ELSE 0 END";
        $diaCaratExpr = "CASE WHEN {$diamondCat} <> {$gemsLiteral} THEN COALESCE({$caratNum}, 0) ELSE 0 END";
        $gemCaratExpr = "CASE WHEN {$diamondCat} = {$gemsLiteral} THEN COALESCE({$caratNum}, 0) ELSE 0 END";

        $descExpr = "TRIM(CONCAT_WS(' ', NULLIF({$pc}.cut, ''), NULLIF({$pc}.shape, ''), NULLIF({$pc}.color, ''), NULLIF({$pc}.clarity, ''), NULLIF(TRIM(IFNULL({$sii}.carat, '')), '')))";

        $salesPreTax = "GREATEST(0, COALESCE({$sii}.net_amt_with_tax, {$sii}.net_amount, 0) - COALESCE({$sii}.tax_amount, 0))";
        $lineCost = "COALESCE({$pc}.value, 0) * IF(COALESCE({$sii}.quantity, 0) > 0, COALESCE({$sii}.quantity, 0), 1)";

        if ($scope === 'diamond_stone') {
            return "
                DATE_FORMAT({$si}.invoice_date, '%Y-%m-%d') AS sort_iso,
                COALESCE(b.name, '') AS branch,
                DATE_FORMAT({$si}.invoice_date, '%d-%m-%Y') AS date,
                {$ledgerExpr} AS ledger_name,
                {$si}.invoice_no AS invoice_no,
                IFNULL({$si}.sales_person, '') AS sales_person,
                IFNULL(p.article, '') AS article,
                IFNULL({$sii}.barcode, '') AS barcode,
                {$descExpr} AS description,
                COALESCE(NULLIF(TRIM({$sii}.product_name), ''), p.name, '') AS product,
                IFNULL(cat.name, '') AS category,
                COALESCE({$sii}.quantity, 0) AS qty,
                IFNULL({$sii}.carat, '') AS purity_label,
                {$purityExpr} AS purity_num,
                COALESCE({$sii}.gross_weight, 0) AS gross_weight,
                {$diamondWtExpr} AS diamond_wt,
                {$gemWtExpr} AS gemstone_wt,
                {$diaCaratExpr} AS diamond_carat,
                {$gemCaratExpr} AS gemstone_carat,
                COALESCE({$sii}.net_weight, 0) AS net_weight,
                COALESCE({$sii}.final_weight, 0) AS final_weight,
                COALESCE({$sii}.rate, 0) AS rate,
                {$metalRateExpr} AS metal_rate,
                {$metalValExpr} AS metal_value,
                COALESCE({$pc}.value, 0) AS char_value,
                IFNULL({$sii}.calculation_type, '') AS calculation_type,
                COALESCE({$sii}.making_amount, 0) AS making_amount,
                COALESCE({$sii}.amount, 0) AS amount,
                COALESCE({$sii}.tax_amount, 0) AS tax_amount,
                COALESCE({$sii}.net_amount, 0) AS net_amount,
                COALESCE({$sii}.net_amt_with_tax, 0) AS net_amt_with_tax,
                {$salesPreTax} AS sales_pre_tax,
                COALESCE({$sii}.stone_amount, 0) AS stone_amount,
                COALESCE({$sii}.diamond_amount, 0) AS diamond_amount,
                IFNULL({$sii}.diamond_category, '') AS diamond_category,
                {$barcodedExpr} AS barcoded_date,
                {$lineCost} AS line_cost_est
            ";
        }

        return "
            DATE_FORMAT({$si}.invoice_date, '%Y-%m-%d') AS sort_iso,
            COALESCE(b.name, '') AS branch,
            DATE_FORMAT({$si}.invoice_date, '%d-%m-%Y') AS date,
            {$ledgerExpr} AS ledger_name,
            {$si}.invoice_no AS invoice_no,
            IFNULL({$si}.sales_person, '') AS sales_person,
            IFNULL(p.article, '') AS article,
            IFNULL({$sii}.barcode, '') AS barcode,
            COALESCE(NULLIF(TRIM({$sii}.product_name), ''), p.name, '') AS product,
            IFNULL(cat.name, '') AS category,
            COALESCE({$sii}.quantity, 0) AS qty,
            {$purityExpr} AS purity_num,
            COALESCE({$sii}.gross_weight, 0) AS gross_weight,
            {$stoneWExpr} AS stone_weight,
            COALESCE({$sii}.net_weight, 0) AS net_weight,
            COALESCE({$sii}.final_weight, 0) AS final_weight,
            COALESCE({$sii}.rate, 0) AS rate,
            {$metalRateExpr} AS metal_rate,
            {$metalValExpr} AS metal_value,
            COALESCE({$pc}.value, 0) AS char_value,
            IFNULL({$sii}.calculation_type, '') AS calculation_type,
            COALESCE({$sii}.making_amount, 0) AS making_amount,
            COALESCE({$sii}.amount, 0) AS amount,
            COALESCE({$sii}.tax_amount, 0) AS tax_amount,
            COALESCE({$sii}.net_amount, 0) AS net_amount,
            COALESCE({$sii}.net_amt_with_tax, 0) AS net_amt_with_tax,
            {$salesPreTax} AS sales_pre_tax,
            COALESCE({$sii}.stone_amount, 0) AS stone_amount,
            COALESCE({$sii}.diamond_amount, 0) AS diamond_amount,
            {$barcodedExpr} AS barcoded_date,
            {$lineCost} AS line_cost_est
        ";
    }
}

if (!function_exists('auragold_financial_analysis_run_query')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_financial_analysis_run_query(mysqli $conn, string $sql): array {
        $out = [];
        try {
            $q = @mysqli_query($conn, $sql);
        } catch (Throwable $e) {
            return [];
        }
        if (!$q) {
            return $out;
        }
        while ($row = mysqli_fetch_assoc($q)) {
            $out[] = $row;
        }
        mysqli_free_result($q);
        return $out;
    }
}

if (!function_exists('auragold_financial_fmt')) {
    function auragold_financial_fmt($v, int $decimals): string {
        if ($v === null || $v === '') {
            return '';
        }
        $x = (float) $v;
        if (!is_finite($x)) {
            return '';
        }
        return number_format($x, $decimals, '.', '');
    }
}

if (!function_exists('auragold_financial_purity_display')) {
    function auragold_financial_purity_display(float $p): string {
        if ($p <= 0) {
            return '';
        }
        if ($p <= 1) {
            return auragold_financial_fmt($p * 100, 2);
        }
        if ($p <= 100) {
            return auragold_financial_fmt($p, 2);
        }
        return auragold_financial_fmt($p, 2);
    }
}

if (!function_exists('auragold_fetch_financial_analysis_sale_lines')) {
    /**
     * @return array<int, array<string, string>>
     */
    function auragold_fetch_financial_analysis_sale_lines(mysqli $conn, int $effBranchId, string $scope): array {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $scope = auragold_financial_analysis_normalize_scope($scope);
        $metalIds = auragold_financial_analysis_metal_ids($conn, $scope);
        if ($metalIds === []) {
            return [];
        }
        $idList = implode(',', array_map('intval', $metalIds));
        // Imitation/watches use the same column shape as gold/silver analysis.
        $selectScope = $scope === 'diamond_stone' ? 'diamond_stone' : 'gold_silver';

        $raw = [];
        $templates = [
            ['si' => 'si', 'sii' => 'sii', 'from' => 'tbl_sale_invoices si INNER JOIN tbl_sale_invoice_items sii ON si.id = sii.invoice_id'],
        ];
        if (auragold_financial_analysis_tbl_exists($conn, 'tbl_pos_sale_invoices')
            && auragold_financial_analysis_tbl_exists($conn, 'tbl_pos_sale_invoice_items')) {
            $templates[] = [
                'si' => 'psi',
                'sii' => 'psii',
                'from' => 'tbl_pos_sale_invoices psi INNER JOIN tbl_pos_sale_invoice_items psii ON psi.id = psii.invoice_id',
            ];
        }

        foreach ($templates as $tpl) {
            $si = $tpl['si'];
            $sii = $tpl['sii'];
            $from = $tpl['from'];
            $invTable = strpos($from, 'tbl_pos_sale') === 0 ? 'tbl_pos_sale_invoices' : 'tbl_sale_invoices';
            $pc = 'pc';
            $branchW = auragold_financial_analysis_branch_where($conn, $effBranchId, $si, $pc, $invTable);
            $select = auragold_financial_analysis_base_select($conn, $si, $sii, $selectScope);

            $metalWhere = $scope === 'gold_silver'
                ? auragold_financial_analysis_gold_silver_metal_predicate($si, $sii, $pc, $idList)
                : "COALESCE({$pc}.metal_id, 0) IN ({$idList})";

            $sqlOne = "
                SELECT {$select}
                FROM {$from}
                LEFT JOIN tbl_products p ON p.id = {$sii}.product_id
                LEFT JOIN tbl_categories cat ON cat.id = p.category_id
                LEFT JOIN tbl_product_characteristics {$pc} ON {$pc}.id = {$sii}.product_characteristic_id AND {$pc}.product_id = {$sii}.product_id AND {$pc}.status = 1
                LEFT JOIN tbl_metal m ON m.id = {$pc}.metal_id
                LEFT JOIN tbl_branches b ON b.id = COALESCE(NULLIF({$si}.branch_id, 0), {$pc}.branch_id)
                WHERE {$sii}.status = 1
                AND LOWER(TRIM(IFNULL({$si}.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
                AND {$metalWhere}
                {$branchW}
                ORDER BY sort_iso DESC, invoice_no DESC, article ASC
            ";
            $raw = array_merge($raw, auragold_financial_analysis_run_query($conn, $sqlOne));
        }

        usort($raw, static function (array $a, array $b): int {
            $c = strcmp((string) ($b['sort_iso'] ?? ''), (string) ($a['sort_iso'] ?? ''));
            if ($c !== 0) {
                return $c;
            }
            $c = strcmp((string) ($b['invoice_no'] ?? ''), (string) ($a['invoice_no'] ?? ''));
            if ($c !== 0) {
                return $c;
            }
            return strcmp((string) ($a['article'] ?? ''), (string) ($b['article'] ?? ''));
        });

        $rows = [];
        foreach ($raw as $r) {
            if ($scope !== 'diamond_stone') {
                $pnum = (float) ($r['purity_num'] ?? 0);
                $salesPre = (float) ($r['sales_pre_tax'] ?? 0);
                $lineCost = (float) ($r['line_cost_est'] ?? 0);
                $profit = $salesPre - $lineCost;
                $profitPer = $salesPre > 0.00001 ? ($profit / $salesPre) * 100 : 0.0;

                $rows[] = [
                    'branch' => (string) ($r['branch'] ?? ''),
                    'date' => (string) ($r['date'] ?? ''),
                    'ledger_name' => (string) ($r['ledger_name'] ?? ''),
                    'invoice_no' => (string) ($r['invoice_no'] ?? ''),
                    'sales_person' => (string) ($r['sales_person'] ?? ''),
                    'article' => (string) ($r['article'] ?? ''),
                    'barcode' => (string) ($r['barcode'] ?? ''),
                    'product' => (string) ($r['product'] ?? ''),
                    'category' => (string) ($r['category'] ?? ''),
                    'pcs' => auragold_financial_fmt($r['qty'] ?? 0, 2),
                    'purity' => auragold_financial_purity_display($pnum),
                    'purity_per' => auragold_financial_purity_display($pnum),
                    'gross_wt' => auragold_financial_fmt($r['gross_weight'] ?? 0, 3),
                    'stone_wt' => auragold_financial_fmt($r['stone_weight'] ?? 0, 3),
                    'net_wt' => auragold_financial_fmt($r['net_weight'] ?? 0, 3),
                    'final_wt' => auragold_financial_fmt($r['final_weight'] ?? 0, 3),
                    'gold_rate' => auragold_financial_fmt($r['rate'] ?? 0, 2),
                    'current_gold_rate' => auragold_financial_fmt($r['metal_rate'] ?? 0, 2),
                    'metal_amt' => auragold_financial_fmt($r['metal_value'] ?? 0, 2),
                    'metal_cost' => auragold_financial_fmt($r['char_value'] ?? 0, 2),
                    'making_type' => (string) ($r['calculation_type'] ?? ''),
                    'making_rate' => '',
                    'making_amt' => auragold_financial_fmt($r['making_amount'] ?? 0, 2),
                    'collected_making' => auragold_financial_fmt($r['making_amount'] ?? 0, 2),
                    'collected_making_charge' => auragold_financial_fmt($r['making_amount'] ?? 0, 2),
                    'making_cost' => '',
                    'making_profit' => '',
                    'stone_charge' => auragold_financial_fmt($r['stone_amount'] ?? 0, 2),
                    'stone_cost' => '',
                    'stone_profit' => '',
                    'other_charges' => auragold_financial_fmt(0, 2),
                    'discount' => auragold_financial_fmt(0, 2),
                    'discount_per' => auragold_financial_fmt(0, 2),
                    'net_amount' => auragold_financial_fmt($r['net_amt_with_tax'] ?? $r['net_amount'] ?? 0, 2),
                    'tax_amount' => auragold_financial_fmt($r['tax_amount'] ?? 0, 2),
                    'sales_amount' => auragold_financial_fmt($salesPre, 2),
                    'cost_price' => auragold_financial_fmt($lineCost, 2),
                    'profit' => auragold_financial_fmt($profit, 2),
                    'profit_per' => auragold_financial_fmt($profitPer, 2),
                    'supplier_name' => '',
                    'barcoded_date' => (string) ($r['barcoded_date'] ?? ''),
                ];
            } else {
                $pnum = (float) ($r['purity_num'] ?? 0);
                $purityLabel = trim((string) ($r['purity_label'] ?? ''));
                $purityShow = $purityLabel !== '' ? $purityLabel : auragold_financial_purity_display($pnum);
                $salesPre = (float) ($r['sales_pre_tax'] ?? 0);
                $lineCost = (float) ($r['line_cost_est'] ?? 0);
                $profit = $salesPre - $lineCost;
                $profitPer = $salesPre > 0.00001 ? ($profit / $salesPre) * 100 : 0.0;
                $mk = (float) ($r['making_amount'] ?? 0);

                $rows[] = [
                    'branch' => (string) ($r['branch'] ?? ''),
                    'date' => (string) ($r['date'] ?? ''),
                    'ledger_name' => (string) ($r['ledger_name'] ?? ''),
                    'invoice_no' => (string) ($r['invoice_no'] ?? ''),
                    'sales_person' => (string) ($r['sales_person'] ?? ''),
                    'article' => (string) ($r['article'] ?? ''),
                    'barcode' => (string) ($r['barcode'] ?? ''),
                    'description' => trim((string) ($r['description'] ?? '')) !== '' ? trim((string) $r['description']) : (string) ($r['product'] ?? ''),
                    'product' => (string) ($r['product'] ?? ''),
                    'category' => (string) ($r['category'] ?? ''),
                    'pcs' => auragold_financial_fmt($r['qty'] ?? 0, 2),
                    'purity' => $purityShow,
                    'purity_per' => auragold_financial_purity_display($pnum),
                    'gross_wt' => auragold_financial_fmt($r['gross_weight'] ?? 0, 3),
                    'diamond_wt' => auragold_financial_fmt($r['diamond_wt'] ?? 0, 3),
                    'gemstone_wt' => auragold_financial_fmt($r['gemstone_wt'] ?? 0, 3),
                    'diamond_carat' => auragold_financial_fmt($r['diamond_carat'] ?? 0, 2),
                    'gemstone_carat' => auragold_financial_fmt($r['gemstone_carat'] ?? 0, 2),
                    'net_wt' => auragold_financial_fmt($r['net_weight'] ?? 0, 3),
                    'final_wt' => auragold_financial_fmt($r['final_weight'] ?? 0, 3),
                    'gold_rate' => auragold_financial_fmt($r['rate'] ?? 0, 2),
                    'current_gold_rate' => auragold_financial_fmt($r['metal_rate'] ?? 0, 2),
                    'metal_amt' => auragold_financial_fmt($r['metal_value'] ?? 0, 2),
                    'metal_cost' => auragold_financial_fmt($r['char_value'] ?? 0, 2),
                    'making_type' => (string) ($r['calculation_type'] ?? ''),
                    'making_rate' => '',
                    'making_amt' => auragold_financial_fmt($mk, 2),
                    'discounted_amt' => '',
                    'making_cost' => '',
                    'making_profit' => '',
                    'discount' => auragold_financial_fmt(0, 2),
                    'discount_per' => auragold_financial_fmt(0, 2),
                    'net_amount' => auragold_financial_fmt($r['net_amt_with_tax'] ?? $r['net_amount'] ?? 0, 2),
                    'tax_amt' => auragold_financial_fmt($r['tax_amount'] ?? 0, 2),
                    'sales_amount' => auragold_financial_fmt($salesPre, 2),
                    'cost_price' => auragold_financial_fmt($lineCost, 2),
                    'profit' => auragold_financial_fmt($profit, 2),
                    'profit_per' => auragold_financial_fmt($profitPer, 2),
                    'other_charges' => auragold_financial_fmt(0, 2),
                    'barcoded_date' => (string) ($r['barcoded_date'] ?? ''),
                    'supplier_name' => '',
                    'collected_making_charge' => auragold_financial_fmt($mk, 2),
                    'collected_making' => auragold_financial_fmt($mk, 2),
                    'gemstone_charge' => auragold_financial_fmt($r['stone_amount'] ?? 0, 2),
                    'gemstone_charge_collected' => auragold_financial_fmt($r['stone_amount'] ?? 0, 2),
                    'diamond_charge' => auragold_financial_fmt($r['diamond_amount'] ?? 0, 2),
                    'diamond_charge_collected' => auragold_financial_fmt($r['diamond_amount'] ?? 0, 2),
                    'discount_type' => '',
                ];
            }
        }
        return $rows;
    }
}
