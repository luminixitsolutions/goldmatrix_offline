<?php
/**
 * Purchase invoice + purchase quotation line rows for Purchase Financial Analysis.
 */

require_once __DIR__ . '/auragold_sale_financial_analysis_data.php';

if (!function_exists('auragold_purchase_financial_fy_default_range')) {
    function auragold_purchase_financial_fy_default_range(): array {
        $today = new DateTimeImmutable('today');
        $y = (int) $today->format('Y');
        $m = (int) $today->format('n');
        $fyStart = $m >= 4 ? $y : ($y - 1);
        $from = sprintf('%04d-04-01', $fyStart);
        $to = sprintf('%04d-03-31', $fyStart + 1);
        $label = sprintf('01-04-%d - 31-03-%d', $fyStart, $fyStart + 1);
        return ['from' => $from, 'to' => $to, 'label' => $label];
    }
}

if (!function_exists('auragold_purchase_financial_parse_date_param')) {
    function auragold_purchase_financial_parse_date_param(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw)) {
            return $raw;
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $raw, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return '';
    }
}

if (!function_exists('auragold_purchase_financial_resolve_dates')) {
    /** @return array{from:string,to:string,label:string} */
    function auragold_purchase_financial_resolve_dates(): array {
        $def = auragold_purchase_financial_fy_default_range();
        $from = auragold_purchase_financial_parse_date_param((string) ($_GET['date_from'] ?? ''));
        $to = auragold_purchase_financial_parse_date_param((string) ($_GET['date_to'] ?? ''));
        if ($from === '' || $to === '') {
            return $def;
        }
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $df = DateTimeImmutable::createFromFormat('Y-m-d', $from);
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $to);
        $label = ($df && $dt)
            ? $df->format('d-m-Y') . ' - ' . $dt->format('d-m-Y')
            : $def['label'];
        return ['from' => $from, 'to' => $to, 'label' => $label];
    }
}

if (!function_exists('auragold_purchase_financial_int_list_param')) {
    /** @return int[] */
    function auragold_purchase_financial_int_list_param(string $key): array {
        if (!isset($_GET[$key])) {
            return [];
        }
        $raw = $_GET[$key];
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        return array_values(array_unique(array_filter(array_map('intval', $raw), static function ($x) {
            return $x > 0;
        })));
    }
}

if (!function_exists('auragold_purchase_financial_str_list_param')) {
    /** @return string[] */
    function auragold_purchase_financial_str_list_param(string $key): array {
        if (!isset($_GET[$key])) {
            return [];
        }
        $raw = $_GET[$key];
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $out = [];
        foreach ($raw as $v) {
            $v = trim((string) $v);
            if ($v !== '') {
                $out[] = $v;
            }
        }
        return array_values(array_unique($out));
    }
}

if (!function_exists('auragold_purchase_financial_parse_filters')) {
    /** @return array<string, mixed> */
    function auragold_purchase_financial_parse_filters(): array {
        $dates = auragold_purchase_financial_resolve_dates();
        $above = trim((string) ($_GET['adv_above_amount'] ?? ''));
        $gross = trim((string) ($_GET['adv_gross_wt'] ?? ''));
        $voucher = strtolower(trim((string) ($_GET['adv_voucher_type'] ?? '')));
        if (!in_array($voucher, ['pi', 'pq'], true)) {
            $voucher = '';
        }
        return [
            'date_from' => $dates['from'],
            'date_to' => $dates['to'],
            'branch_ids' => auragold_purchase_financial_int_list_param('adv_branch'),
            'supplier_ids' => auragold_purchase_financial_int_list_param('adv_supplier'),
            'purchase_person' => trim((string) ($_GET['adv_purchase_person'] ?? '')),
            'voucher_type' => $voucher,
            'metal_ids' => auragold_purchase_financial_int_list_param('adv_metal'),
            'product_ids' => auragold_purchase_financial_int_list_param('adv_product'),
            'category_ids' => auragold_purchase_financial_int_list_param('adv_category'),
            'articles' => auragold_purchase_financial_str_list_param('adv_article'),
            'currency' => trim((string) ($_GET['adv_currency'] ?? '')),
            'above_amount' => ($above !== '' && is_numeric($above)) ? (float) $above : null,
            'barcode' => trim((string) ($_GET['adv_barcode'] ?? '')),
            'invoice_no' => trim((string) ($_GET['adv_invoice_no'] ?? '')),
            'gross_wt' => ($gross !== '' && is_numeric($gross)) ? (float) $gross : null,
            'comment' => trim((string) ($_GET['adv_comment'] ?? '')),
            'account_no' => trim((string) ($_GET['adv_account_no'] ?? '')),
        ];
    }
}

if (!function_exists('auragold_purchase_financial_filter_count')) {
    function auragold_purchase_financial_filter_count(array $filters): int {
        $n = 0;
        if (!empty($filters['branch_ids'])) {
            $n++;
        }
        if (!empty($filters['supplier_ids'])) {
            $n++;
        }
        if (($filters['purchase_person'] ?? '') !== '') {
            $n++;
        }
        if (($filters['voucher_type'] ?? '') !== '') {
            $n++;
        }
        if (!empty($filters['metal_ids'])) {
            $n++;
        }
        if (!empty($filters['product_ids'])) {
            $n++;
        }
        if (!empty($filters['category_ids'])) {
            $n++;
        }
        if (!empty($filters['articles'])) {
            $n++;
        }
        if (($filters['currency'] ?? '') !== '') {
            $n++;
        }
        if ($filters['above_amount'] !== null) {
            $n++;
        }
        if (($filters['barcode'] ?? '') !== '') {
            $n++;
        }
        if (($filters['invoice_no'] ?? '') !== '') {
            $n++;
        }
        if ($filters['gross_wt'] !== null) {
            $n++;
        }
        if (($filters['comment'] ?? '') !== '') {
            $n++;
        }
        if (($filters['account_no'] ?? '') !== '') {
            $n++;
        }
        return $n;
    }
}

if (!function_exists('auragold_purchase_financial_branch_groups')) {
    /** @return list<array{main: array{id:int,name:string}, subs: list<array{id:int,name:string}>}> */
    function auragold_purchase_financial_branch_groups(mysqli $conn): array {
        if (!function_exists('auragold_um_branch_picker_groups')) {
            require_once __DIR__ . '/user_management_schema.php';
        }
        global $conn_master;
        $groups = auragold_um_branch_picker_groups($conn, $conn_master ?? null);
        return is_array($groups) ? $groups : [];
    }
}

if (!function_exists('auragold_purchase_financial_filter_options')) {
    /** @return array<string, array<int, array<string, mixed>>> */
    function auragold_purchase_financial_filter_options(mysqli $conn): array {
        $safeList = static function (string $sql) {
            try {
                $rows = getList($sql);
                return is_array($rows) ? $rows : [];
            } catch (Throwable $e) {
                return [];
            }
        };
        return [
            'branch_groups' => auragold_purchase_financial_branch_groups($conn),
            'branches' => $safeList("SELECT id, name FROM tbl_branches WHERE IFNULL(status, 1) = 1 ORDER BY name ASC"),
            'metals' => $safeList("SELECT id, display_name AS name FROM tbl_metal WHERE IFNULL(status, 1) = 1 ORDER BY display_name ASC"),
            'products' => $safeList("SELECT id, name FROM tbl_products WHERE IFNULL(status, 1) = 1 ORDER BY name ASC LIMIT 800"),
            'categories' => $safeList("SELECT id, name FROM tbl_categories WHERE IFNULL(status, 1) = 1 ORDER BY name ASC"),
            'suppliers' => $safeList("
                SELECT DISTINCT c.id, TRIM(c.name) AS name
                FROM tbl_customers c
                WHERE IFNULL(c.status, 1) = 1
                AND (
                    EXISTS (SELECT 1 FROM tbl_purchase_invoices pi WHERE pi.supplier_id = c.id)
                    OR EXISTS (SELECT 1 FROM tbl_purchase_quotations pq WHERE pq.supplier_id = c.id)
                )
                ORDER BY name ASC
            "),
            'articles' => $safeList("
                SELECT DISTINCT TRIM(p.article) AS article
                FROM tbl_products p
                WHERE p.article IS NOT NULL AND TRIM(p.article) != '' AND IFNULL(p.status, 1) = 1
                ORDER BY article ASC
                LIMIT 400
            "),
            'currencies' => $safeList("
                SELECT DISTINCT TRIM(currency) AS currency FROM (
                    SELECT currency FROM tbl_purchase_invoices WHERE currency IS NOT NULL AND TRIM(currency) != ''
                    UNION
                    SELECT currency FROM tbl_purchase_quotations WHERE currency IS NOT NULL AND TRIM(currency) != ''
                ) u ORDER BY currency ASC
            "),
            'persons' => $safeList("
                SELECT DISTINCT TRIM(purchase_person) AS purchase_person FROM (
                    SELECT purchase_person FROM tbl_purchase_invoices WHERE purchase_person IS NOT NULL AND TRIM(purchase_person) != ''
                    UNION
                    SELECT purchase_person FROM tbl_purchase_quotations WHERE purchase_person IS NOT NULL AND TRIM(purchase_person) != ''
                ) u ORDER BY purchase_person ASC
            "),
        ];
    }
}

if (!function_exists('auragold_purchase_financial_branch_sql')) {
    function auragold_purchase_financial_branch_sql(
        mysqli $conn,
        int $effBranchId,
        array $filterBranchIds,
        string $hdrAlias,
        string $pcAlias,
        string $invTable
    ): string {
        if (!empty($filterBranchIds)) {
            $ids = implode(',', array_map('intval', $filterBranchIds));
            $hasBr = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $invTable, 'branch_id');
            if ($hasBr) {
                return " AND (
                    ({$hdrAlias}.branch_id IS NOT NULL AND {$hdrAlias}.branch_id > 0 AND {$hdrAlias}.branch_id IN ({$ids}))
                    OR (({$hdrAlias}.branch_id IS NULL OR {$hdrAlias}.branch_id = 0) AND {$pcAlias}.branch_id IN ({$ids}))
                )";
            }
            return " AND {$pcAlias}.branch_id IN ({$ids})";
        }
        return auragold_financial_analysis_branch_where($conn, $effBranchId, $hdrAlias, $pcAlias, $invTable);
    }
}

if (!function_exists('auragold_purchase_financial_extra_where_sql')) {
    function auragold_purchase_financial_extra_where_sql(
        mysqli $conn,
        array $filters,
        string $hdrAlias,
        string $itemAlias,
        string $pcAlias,
        string $pAlias,
        string $cAlias,
        string $docNoCol
    ): string {
        $sql = '';
        if (!empty($filters['supplier_ids'])) {
            $ids = implode(',', array_map('intval', $filters['supplier_ids']));
            $sql .= " AND {$hdrAlias}.supplier_id IN ({$ids})";
        }
        if (($filters['purchase_person'] ?? '') !== '') {
            $pp = mysqli_real_escape_string($conn, (string) $filters['purchase_person']);
            $sql .= " AND {$hdrAlias}.purchase_person LIKE '%{$pp}%'";
        }
        if (!empty($filters['metal_ids'])) {
            $ids = implode(',', array_map('intval', $filters['metal_ids']));
            $sql .= " AND {$pcAlias}.metal_id IN ({$ids})";
        }
        if (!empty($filters['product_ids'])) {
            $ids = implode(',', array_map('intval', $filters['product_ids']));
            $sql .= " AND {$itemAlias}.product_id IN ({$ids})";
        }
        if (!empty($filters['category_ids'])) {
            $ids = implode(',', array_map('intval', $filters['category_ids']));
            $sql .= " AND {$pAlias}.category_id IN ({$ids})";
        }
        if (!empty($filters['articles'])) {
            $parts = [];
            foreach ($filters['articles'] as $art) {
                $parts[] = "'" . mysqli_real_escape_string($conn, (string) $art) . "'";
            }
            if ($parts !== []) {
                $sql .= ' AND ' . $pAlias . '.article IN (' . implode(',', $parts) . ')';
            }
        }
        if (($filters['currency'] ?? '') !== '') {
            $cur = mysqli_real_escape_string($conn, (string) $filters['currency']);
            $sql .= " AND {$hdrAlias}.currency = '{$cur}'";
        }
        if ($filters['above_amount'] !== null) {
            $amt = (float) $filters['above_amount'];
            $sql .= " AND COALESCE(NULLIF({$itemAlias}.purchase_amount, 0), {$itemAlias}.net_amount, {$itemAlias}.amount, 0) >= {$amt}";
        }
        if (($filters['barcode'] ?? '') !== '') {
            $bc = mysqli_real_escape_string($conn, (string) $filters['barcode']);
            $sql .= " AND {$itemAlias}.barcode LIKE '%{$bc}%'";
        }
        if (($filters['invoice_no'] ?? '') !== '') {
            $inv = mysqli_real_escape_string($conn, (string) $filters['invoice_no']);
            $sql .= " AND {$hdrAlias}.{$docNoCol} LIKE '%{$inv}%'";
        }
        if ($filters['gross_wt'] !== null) {
            $gw = (float) $filters['gross_wt'];
            $sql .= " AND COALESCE(NULLIF({$itemAlias}.metal_weight, 0), {$itemAlias}.gross_weight, 0) >= {$gw}";
        }
        if (($filters['comment'] ?? '') !== '') {
            $cm = mysqli_real_escape_string($conn, (string) $filters['comment']);
            $sql .= " AND {$hdrAlias}.comment LIKE '%{$cm}%'";
        }
        if (($filters['account_no'] ?? '') !== '') {
            $ac = mysqli_real_escape_string($conn, (string) $filters['account_no']);
            $hdrTable = ($hdrAlias === 'pq') ? 'tbl_purchase_quotations' : 'tbl_purchase_invoices';
            $refCol = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $hdrTable, 'ref_no')
                ? " OR {$hdrAlias}.ref_no LIKE '%{$ac}%'"
                : '';
            $sql .= " AND ({$cAlias}.identity_no LIKE '%{$ac}%' OR {$cAlias}.national_id LIKE '%{$ac}%'{$refCol})";
        }
        return $sql;
    }
}

if (!function_exists('auragold_purchase_financial_payment_key')) {
    function auragold_purchase_financial_payment_key(string $paymentType): string {
        $t = strtolower(trim($paymentType));
        if ($t === '') {
            return '';
        }
        if ($t === 'cash') {
            return 'cash';
        }
        if ($t === 'bank') {
            return 'bank';
        }
        if ($t === 'cheque') {
            return 'cheque';
        }
        if ($t === 'upi') {
            return 'upi';
        }
        if ($t === 'card') {
            return 'card';
        }
        if (strpos($t, 'exch') !== false || strpos($t, 'm. exch') !== false) {
            return 'metal_exch';
        }
        if (strpos($t, 'scrap') !== false || strpos($t, 'old') !== false) {
            return 'old_jew';
        }
        return '';
    }
}

if (!function_exists('auragold_purchase_financial_empty_payments')) {
    /** @return array<string, float> */
    function auragold_purchase_financial_empty_payments(): array {
        return [
            'cash' => 0.0,
            'bank' => 0.0,
            'cheque' => 0.0,
            'upi' => 0.0,
            'card' => 0.0,
            'metal_exch_amt' => 0.0,
            'metal_exch_wt' => 0.0,
            'old_jew_amt' => 0.0,
            'old_jew_wt' => 0.0,
        ];
    }
}

if (!function_exists('auragold_purchase_financial_load_payments')) {
    /**
     * @param int[] $docIds
     * @return array<int, array<string, float>>
     */
    function auragold_purchase_financial_load_payments(mysqli $conn, string $docKind, array $docIds): array {
        $docIds = array_values(array_filter(array_map('intval', $docIds), static function ($x) {
            return $x > 0;
        }));
        if ($docIds === []) {
            return [];
        }
        $idList = implode(',', $docIds);
        $table = $docKind === 'pq' ? 'tbl_purchase_quotation_payments' : 'tbl_purchase_invoice_payments';
        $fk = $docKind === 'pq' ? 'quotation_id' : 'invoice_id';
        if (!auragold_financial_analysis_tbl_exists($conn, $table)) {
            return [];
        }
        $sql = "SELECT {$fk} AS doc_id, payment_type, amount, quantity, weight, payment_details
                FROM {$table}
                WHERE {$fk} IN ({$idList}) AND IFNULL(status, 1) = 1";
        $rows = auragold_financial_analysis_run_query($conn, $sql);
        $out = [];
        foreach ($rows as $row) {
            $docId = (int) ($row['doc_id'] ?? 0);
            if ($docId <= 0) {
                continue;
            }
            if (!isset($out[$docId])) {
                $out[$docId] = auragold_purchase_financial_empty_payments();
            }
            $key = auragold_purchase_financial_payment_key((string) ($row['payment_type'] ?? ''));
            if ($key === '') {
                continue;
            }
            $amt = (float) ($row['amount'] ?? 0);
            if ($key === 'metal_exch') {
                $out[$docId]['metal_exch_amt'] += $amt;
                $wt = (float) ($row['weight'] ?? 0);
                if ($wt <= 0 && !empty($row['payment_details'])) {
                    $json = json_decode((string) $row['payment_details'], true);
                    if (is_array($json)) {
                        $wt = (float) ($json['metal_exchange_gross_wt'] ?? $json['gross_wt'] ?? 0);
                    }
                }
                $out[$docId]['metal_exch_wt'] += $wt;
                continue;
            }
            if ($key === 'old_jew') {
                $out[$docId]['old_jew_amt'] += $amt;
                $wt = (float) ($row['weight'] ?? 0);
                if ($wt <= 0 && !empty($row['payment_details'])) {
                    $json = json_decode((string) $row['payment_details'], true);
                    if (is_array($json)) {
                        $wt = (float) ($json['gross_wt'] ?? $json['weight'] ?? 0);
                    }
                }
                $out[$docId]['old_jew_wt'] += $wt;
                continue;
            }
            $out[$docId][$key] += $amt;
        }
        return $out;
    }
}

if (!function_exists('auragold_purchase_financial_format_payments')) {
    /** @return array<string, string> */
    function auragold_purchase_financial_format_payments(array $pay): array {
        return [
            'cash' => auragold_financial_fmt($pay['cash'] ?? 0, 2),
            'bank' => auragold_financial_fmt($pay['bank'] ?? 0, 2),
            'cheque' => auragold_financial_fmt($pay['cheque'] ?? 0, 2),
            'upi' => auragold_financial_fmt($pay['upi'] ?? 0, 2),
            'card' => auragold_financial_fmt($pay['card'] ?? 0, 2),
            'metal_exch_amt' => auragold_financial_fmt($pay['metal_exch_amt'] ?? 0, 2),
            'metal_exch_wt' => auragold_financial_fmt($pay['metal_exch_wt'] ?? 0, 3),
            'old_jew_amt' => auragold_financial_fmt($pay['old_jew_amt'] ?? 0, 2),
            'old_jew_wt' => auragold_financial_fmt($pay['old_jew_wt'] ?? 0, 3),
        ];
    }
}

if (!function_exists('auragold_purchase_financial_item_active_sql')) {
    function auragold_purchase_financial_item_active_sql(mysqli $conn, string $table, string $alias): string {
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $table, 'active')) {
            return "IFNULL({$alias}.active, 1) = 1";
        }
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $table, 'status')) {
            return "IFNULL({$alias}.status, 1) = 1";
        }
        return '1=1';
    }
}

if (!function_exists('auragold_purchase_financial_header_status_sql')) {
    function auragold_purchase_financial_header_status_sql(string $alias): string {
        return "LOWER(TRIM(IFNULL({$alias}.status, ''))) NOT IN ('deleted', 'cancelled', 'void')";
    }
}

if (!function_exists('auragold_purchase_financial_fetch_invoice_lines')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_purchase_financial_fetch_invoice_lines(mysqli $conn, int $effBranchId, array $filters): array {
        if (!auragold_financial_analysis_tbl_exists($conn, 'tbl_purchase_invoices')
            || !auragold_financial_analysis_tbl_exists($conn, 'tbl_purchase_invoice_items')) {
            return [];
        }
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $pi = 'pi';
        $pii = 'pii';
        $pc = 'pc';
        $p = 'p';
        $c = 'c';
        $itemActive = auragold_purchase_financial_item_active_sql($conn, 'tbl_purchase_invoice_items', $pii);
        $branchW = auragold_purchase_financial_branch_sql($conn, $effBranchId, (array) ($filters['branch_ids'] ?? []), $pi, $pc, 'tbl_purchase_invoices');
        $extraW = auragold_purchase_financial_extra_where_sql($conn, $filters, $pi, $pii, $pc, $p, $c, 'invoice_no');
        $dateFromEsc = mysqli_real_escape_string($conn, $dateFrom);
        $dateToEsc = mysqli_real_escape_string($conn, $dateTo);
        $locJoin = '';
        $locExpr = "''";
        if (auragold_financial_analysis_tbl_exists($conn, 'tbl_location')
            && function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_purchase_invoice_items', 'location_id')) {
            $locJoin = 'LEFT JOIN tbl_location loc ON loc.id = pii.location_id';
            $locExpr = "COALESCE(loc.name, '')";
        }
        $mobileExpr = "TRIM(CONCAT_WS(' ', NULLIF(TRIM(c.mobile_country_code), ''), NULLIF(TRIM(c.mobile_no), '')))";

        $sql = "
            SELECT
                'pi' AS doc_kind,
                {$pi}.id AS doc_id,
                {$pi}.invoice_no AS invoice_no,
                COALESCE(b.name, '') AS branch,
                DATE_FORMAT({$pi}.invoice_date, '%d-%m-%Y') AS date,
                DATE_FORMAT({$pi}.invoice_date, '%Y-%m-%d') AS sort_iso,
                IFNULL({$pii}.barcode, '') AS barcode,
                COALESCE(NULLIF(TRIM({$pii}.product_name), ''), p.name, '') AS product,
                {$locExpr} AS location,
                COALESCE(NULLIF({$pii}.metal_weight, 0), {$pii}.gross_weight, 0) AS gross_weight,
                COALESCE({$pii}.final_weight, 0) AS final_weight,
                COALESCE(NULLIF({$pii}.metal_qty, 0), {$pii}.quantity, 0) AS qty,
                COALESCE({$pii}.stone_weight, 0) AS stone_weight,
                COALESCE({$pii}.metal_value, 0) AS metal_value,
                COALESCE({$pii}.making_amount, 0) AS making_amount,
                COALESCE({$pii}.stone_amount, 0) AS stone_amount,
                COALESCE(NULLIF({$pii}.purchase_amount, 0), {$pii}.net_amt_with_tax, {$pii}.net_amount, {$pii}.amount, 0) AS purchase_amount,
                COALESCE({$pii}.net_amt_with_tax, {$pii}.net_amount, {$pii}.amount, 0) AS line_grand_total,
                TRIM(CONCAT_WS(' — ', NULLIF(TRIM({$pi}.against_of), ''), NULLIF(TRIM({$pi}.supplier_name), ''))) AS ledger_name,
                COALESCE({$pi}.discount_amt, 0) AS header_discount,
                COALESCE({$pi}.round_off, 0) AS header_round_off,
                COALESCE({$pi}.balance_amt, 0) AS header_balance_amt,
                COALESCE({$pi}.grand_total, 0) AS header_grand_total,
                IFNULL({$pi}.comment, '') AS comment,
                IFNULL({$pi}.currency, '') AS currency,
                IFNULL(cat.name, '') AS category,
                IFNULL(p.article, '') AS article,
                IFNULL(c.national_id, '') AS national_id,
                {$mobileExpr} AS mobile_no,
                {$pii}.id AS line_id
            FROM tbl_purchase_invoices {$pi}
            INNER JOIN tbl_purchase_invoice_items {$pii} ON {$pii}.invoice_id = {$pi}.id AND {$itemActive}
            LEFT JOIN tbl_products p ON p.id = {$pii}.product_id
            LEFT JOIN tbl_categories cat ON cat.id = p.category_id
            LEFT JOIN tbl_product_characteristics {$pc} ON {$pc}.id = {$pii}.product_characteristic_id AND {$pc}.product_id = {$pii}.product_id
            LEFT JOIN tbl_branches b ON b.id = COALESCE(NULLIF({$pi}.branch_id, 0), {$pc}.branch_id)
            LEFT JOIN tbl_customers c ON c.id = {$pi}.supplier_id AND IFNULL(c.status, 1) = 1
            {$locJoin}
            WHERE " . auragold_purchase_financial_header_status_sql($pi) . "
            AND DATE({$pi}.invoice_date) >= '{$dateFromEsc}'
            AND DATE({$pi}.invoice_date) <= '{$dateToEsc}'
            {$branchW}
            {$extraW}
            ORDER BY sort_iso DESC, {$pi}.id DESC, {$pii}.id ASC
        ";
        return auragold_financial_analysis_run_query($conn, $sql);
    }
}

if (!function_exists('auragold_purchase_financial_fetch_quotation_lines')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_purchase_financial_fetch_quotation_lines(mysqli $conn, int $effBranchId, array $filters): array {
        if (!auragold_financial_analysis_tbl_exists($conn, 'tbl_purchase_quotations')
            || !auragold_financial_analysis_tbl_exists($conn, 'tbl_purchase_quotation_items')) {
            return [];
        }
        $dateFrom = (string) ($filters['date_from'] ?? '');
        $dateTo = (string) ($filters['date_to'] ?? '');
        $pq = 'pq';
        $pqi = 'pqi';
        $pc = 'pc';
        $p = 'p';
        $c = 'c';
        $branchW = auragold_purchase_financial_branch_sql($conn, $effBranchId, (array) ($filters['branch_ids'] ?? []), $pq, $pc, 'tbl_purchase_quotations');
        $extraW = auragold_purchase_financial_extra_where_sql($conn, $filters, $pq, $pqi, $pc, $p, $c, 'quotation_no');
        $dateFromEsc = mysqli_real_escape_string($conn, $dateFrom);
        $dateToEsc = mysqli_real_escape_string($conn, $dateTo);
        $locJoin = '';
        $locExpr = "''";
        if (auragold_financial_analysis_tbl_exists($conn, 'tbl_location')
            && function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_product_characteristics', 'location_id')) {
            $locJoin = 'LEFT JOIN tbl_location loc ON loc.id = pc.location_id';
            $locExpr = "COALESCE(loc.name, '')";
        }
        $mobileExpr = "TRIM(CONCAT_WS(' ', NULLIF(TRIM(c.mobile_country_code), ''), NULLIF(TRIM(c.mobile_no), '')))";

        $sql = "
            SELECT
                'pq' AS doc_kind,
                {$pq}.id AS doc_id,
                {$pq}.quotation_no AS invoice_no,
                COALESCE(b.name, '') AS branch,
                DATE_FORMAT({$pq}.quotation_date, '%d-%m-%Y') AS date,
                DATE_FORMAT({$pq}.quotation_date, '%Y-%m-%d') AS sort_iso,
                IFNULL({$pqi}.barcode, '') AS barcode,
                COALESCE(NULLIF(TRIM({$pqi}.product_name), ''), p.name, '') AS product,
                {$locExpr} AS location,
                COALESCE(NULLIF({$pqi}.metal_weight, 0), {$pqi}.gross_weight, 0) AS gross_weight,
                COALESCE({$pqi}.final_weight, 0) AS final_weight,
                COALESCE(NULLIF({$pqi}.metal_qty, 0), {$pqi}.quantity, 0) AS qty,
                COALESCE({$pqi}.stone_weight, 0) AS stone_weight,
                COALESCE({$pqi}.metal_value, 0) AS metal_value,
                COALESCE({$pqi}.making_amount, 0) AS making_amount,
                COALESCE({$pqi}.stone_amount, 0) AS stone_amount,
                COALESCE(NULLIF({$pqi}.purchase_amount, 0), {$pqi}.net_amount, {$pqi}.amount, 0) AS purchase_amount,
                COALESCE({$pqi}.net_amount, {$pqi}.amount, 0) AS line_grand_total,
                TRIM(CONCAT_WS(' — ', NULLIF(TRIM({$pq}.against_of), ''), NULLIF(TRIM({$pq}.supplier_name), ''))) AS ledger_name,
                COALESCE({$pq}.discount_amt, 0) AS header_discount,
                COALESCE({$pq}.round_off, 0) AS header_round_off,
                COALESCE({$pq}.balance_amt, 0) AS header_balance_amt,
                COALESCE({$pq}.grand_total, 0) AS header_grand_total,
                IFNULL({$pq}.comment, '') AS comment,
                IFNULL({$pq}.currency, '') AS currency,
                IFNULL(cat.name, '') AS category,
                IFNULL(p.article, '') AS article,
                IFNULL(c.national_id, '') AS national_id,
                {$mobileExpr} AS mobile_no,
                {$pqi}.id AS line_id
            FROM tbl_purchase_quotations {$pq}
            INNER JOIN tbl_purchase_quotation_items {$pqi} ON {$pqi}.quotation_id = {$pq}.id
            LEFT JOIN tbl_products p ON p.id = {$pqi}.product_id
            LEFT JOIN tbl_categories cat ON cat.id = p.category_id
            LEFT JOIN tbl_product_characteristics {$pc} ON {$pc}.id = {$pqi}.product_characteristic_id AND {$pc}.product_id = {$pqi}.product_id
            LEFT JOIN tbl_branches b ON b.id = COALESCE(NULLIF({$pq}.branch_id, 0), {$pc}.branch_id)
            LEFT JOIN tbl_customers c ON c.id = {$pq}.supplier_id AND IFNULL(c.status, 1) = 1
            {$locJoin}
            WHERE " . auragold_purchase_financial_header_status_sql($pq) . "
            AND DATE({$pq}.quotation_date) >= '{$dateFromEsc}'
            AND DATE({$pq}.quotation_date) <= '{$dateToEsc}'
            {$branchW}
            {$extraW}
            ORDER BY sort_iso DESC, {$pq}.id DESC, {$pqi}.id ASC
        ";
        return auragold_financial_analysis_run_query($conn, $sql);
    }
}

if (!function_exists('auragold_fetch_purchase_financial_analysis_rows')) {
    /**
     * @return array<int, array<string, string>>
     */
    function auragold_fetch_purchase_financial_analysis_rows(mysqli $conn, int $effBranchId, array $filters): array {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $raw = [];
        $voucherType = (string) ($filters['voucher_type'] ?? '');
        if ($voucherType !== 'pq') {
            $raw = array_merge($raw, auragold_purchase_financial_fetch_invoice_lines($conn, $effBranchId, $filters));
        }
        if ($voucherType !== 'pi') {
            $raw = array_merge($raw, auragold_purchase_financial_fetch_quotation_lines($conn, $effBranchId, $filters));
        }
        if ($raw === []) {
            return [];
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
            return ((int) ($a['line_id'] ?? 0)) <=> ((int) ($b['line_id'] ?? 0));
        });

        $piIds = [];
        $pqIds = [];
        foreach ($raw as $r) {
            $kind = (string) ($r['doc_kind'] ?? '');
            $docId = (int) ($r['doc_id'] ?? 0);
            if ($docId <= 0) {
                continue;
            }
            if ($kind === 'pq') {
                $pqIds[$docId] = $docId;
            } else {
                $piIds[$docId] = $docId;
            }
        }
        $piPay = auragold_purchase_financial_load_payments($conn, 'pi', array_values($piIds));
        $pqPay = auragold_purchase_financial_load_payments($conn, 'pq', array_values($pqIds));

        $seenDocs = [];
        $rows = [];
        foreach ($raw as $r) {
            $kind = (string) ($r['doc_kind'] ?? 'pi');
            $docId = (int) ($r['doc_id'] ?? 0);
            $docKey = $kind . ':' . $docId;
            $isFirst = !isset($seenDocs[$docKey]);
            $seenDocs[$docKey] = true;

            $pay = $isFirst
                ? ($kind === 'pq' ? ($pqPay[$docId] ?? auragold_purchase_financial_empty_payments()) : ($piPay[$docId] ?? auragold_purchase_financial_empty_payments()))
                : auragold_purchase_financial_empty_payments();
            $payFmt = auragold_purchase_financial_format_payments($pay);

            $rows[] = [
                'invoice_no' => (string) ($r['invoice_no'] ?? ''),
                'branch' => (string) ($r['branch'] ?? ''),
                'date' => (string) ($r['date'] ?? ''),
                'barcode' => (string) ($r['barcode'] ?? ''),
                'product' => (string) ($r['product'] ?? ''),
                'location' => (string) ($r['location'] ?? ''),
                'gross_wt' => auragold_financial_fmt($r['gross_weight'] ?? 0, 3),
                'final_wt' => auragold_financial_fmt($r['final_weight'] ?? 0, 3),
                'pcs' => auragold_financial_fmt($r['qty'] ?? 0, 0),
                'stone_wt' => auragold_financial_fmt($r['stone_weight'] ?? 0, 3),
                'metal_amt' => auragold_financial_fmt($r['metal_value'] ?? 0, 2),
                'making_amt' => auragold_financial_fmt($r['making_amount'] ?? 0, 2),
                'stone_amt' => auragold_financial_fmt($r['stone_amount'] ?? 0, 2),
                'purchase_amt' => auragold_financial_fmt($r['purchase_amount'] ?? 0, 2),
                'ledger_name' => (string) ($r['ledger_name'] ?? ''),
                'grand_total' => auragold_financial_fmt($r['line_grand_total'] ?? 0, 2),
                'discount' => $isFirst ? auragold_financial_fmt($r['header_discount'] ?? 0, 2) : '',
                'cash' => $payFmt['cash'],
                'bank' => $payFmt['bank'],
                'cheque' => $payFmt['cheque'],
                'upi' => $payFmt['upi'],
                'round_off' => $isFirst ? auragold_financial_fmt($r['header_round_off'] ?? 0, 2) : '',
                'card' => $payFmt['card'],
                'metal_exch_amt' => $payFmt['metal_exch_amt'],
                'metal_exch_wt' => $payFmt['metal_exch_wt'],
                'old_jew_amt' => $payFmt['old_jew_amt'],
                'old_jew_wt' => $payFmt['old_jew_wt'],
                'balance_amt' => $isFirst ? auragold_financial_fmt($r['header_balance_amt'] ?? 0, 2) : '',
                'comment' => $isFirst ? (string) ($r['comment'] ?? '') : '',
                'currency' => $isFirst ? (string) ($r['currency'] ?? '') : '',
                'category' => (string) ($r['category'] ?? ''),
                'article' => (string) ($r['article'] ?? ''),
                'national_id' => $isFirst ? (string) ($r['national_id'] ?? '') : '',
                'mobile_no' => $isFirst ? (string) ($r['mobile_no'] ?? '') : '',
            ];
        }
        return $rows;
    }
}
