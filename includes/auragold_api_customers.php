<?php
/**
 * Shared helpers for public Customers API.
 */

if (!function_exists('auragold_api_customers_table_exists')) {
    /**
     * @param mysqli $link
     */
    function auragold_api_customers_table_exists($link, string $table): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return false;
        }
        $rs = @mysqli_query($link, "SHOW TABLES LIKE '" . $table . "'");
        $ok = $rs && mysqli_num_rows($rs) > 0;
        if ($rs) {
            mysqli_free_result($rs);
        }
        return $ok;
    }
}

if (!function_exists('auragold_api_customers_fetch_all')) {
    /**
     * @param mysqli $link
     * @return list<array<string,mixed>>
     */
    function auragold_api_customers_fetch_all($link, string $sql): array
    {
        $rs = mysqli_query($link, $sql);
        if (!$rs) {
            return [];
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $rows[] = $row;
        }
        mysqli_free_result($rs);
        return $rows;
    }
}

if (!function_exists('auragold_api_customers_format_row')) {
    /**
     * Normalize one tbl_customers row for JSON output.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    function auragold_api_customers_format_row(array $row): array
    {
        $jsonKeys = ['item_tax_data', 'share_holders_data', 'share_holder_documents', 'nominee_data'];
        foreach ($jsonKeys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $raw = $row[$key];
            if ($raw === null || $raw === '') {
                $row[$key] = ($key === 'item_tax_data') ? null : [];
                continue;
            }
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$key] = $decoded;
                }
            }
        }

        if (array_key_exists('share_holders_data', $row)) {
            $row['share_holders'] = is_array($row['share_holders_data']) ? $row['share_holders_data'] : [];
            unset($row['share_holders_data']);
        }
        if (array_key_exists('share_holder_documents', $row) && !is_array($row['share_holder_documents'])) {
            $row['share_holder_documents'] = [];
        }
        if (array_key_exists('nominee_data', $row)) {
            $row['nominees'] = is_array($row['nominee_data']) ? $row['nominee_data'] : [];
        }

        $intKeys = [
            'id', 'customer_type_id', 'nationality_id', 'country_id', 'group_id',
            'sundry_debtors_id', 'ledger_state_id', 'ledger_city_id', 'status',
        ];
        foreach ($intKeys as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                $row[$k] = (int) $row[$k];
            }
        }

        $boolKeys = ['ledger_name_capital', 'kyc', 'aml', 'bill_to_bill'];
        foreach ($boolKeys as $k) {
            if (array_key_exists($k, $row)) {
                $row[$k] = (int) ($row[$k] ?? 0) === 1;
            }
        }

        $summaryKeys = [
            'total_sale_invoice', 'total_purchase_invoice',
            'total_sale_invoice_amount', 'total_purchase_invoice_amount',
            'total_previous_balance_amount',
        ];
        foreach ($summaryKeys as $k) {
            if (!array_key_exists($k, $row)) {
                continue;
            }
            if (strpos($k, '_amount') !== false || strpos($k, 'balance') !== false) {
                $row[$k] = round((float) ($row[$k] ?? 0), 2);
            } else {
                $row[$k] = (int) ($row[$k] ?? 0);
            }
        }

        if (array_key_exists('customer_type_name', $row)) {
            $row['customer_type_name'] = (string) ($row['customer_type_name'] ?? '');
        }

        return $row;
    }
}

if (!function_exists('auragold_api_customers_build_query')) {
    /**
     * Build SQL to fetch customers with optional summary aggregates.
     *
     * @param mysqli $link
     * @param array{id?:int,customer_id?:int,type?:string,include_summary?:bool} $filters
     */
    function auragold_api_customers_build_query($link, array $filters = []): string
    {
        $filterId         = (int) ($filters['id'] ?? $filters['customer_id'] ?? 0);
        $filterType       = trim((string) ($filters['type'] ?? ''));
        $includeSummary   = !empty($filters['include_summary']);

        $hasSale     = auragold_api_customers_table_exists($link, 'tbl_sale_invoices');
        $hasPurchase = auragold_api_customers_table_exists($link, 'tbl_purchase_invoices');
        $hasLedger   = auragold_api_customers_table_exists($link, 'tbl_customer_ledger');
        $hasBalTbl   = auragold_api_customers_table_exists($link, 'tbl_customer_balance');
        $hasTypes    = auragold_api_customers_table_exists($link, 'tbl_customer_types');

        $typeJoin  = '';
        $typeWhere = '';
        if ($hasTypes) {
            if ($filterType !== '' && strtolower($filterType) !== 'all') {
                $typeJoin = 'INNER JOIN tbl_customer_types ct ON ct.id = c.customer_type_id';
                $escType = mysqli_real_escape_string($link, strtolower($filterType));
                $typeWhere = " AND LOWER(TRIM(IFNULL(ct.name, ''))) = '{$escType}'
                               AND IFNULL(ct.status, 1) = 1";
            } else {
                $typeJoin = 'LEFT JOIN tbl_customer_types ct ON ct.id = c.customer_type_id';
            }
        }

        $selectExtra = $hasTypes ? ', ct.name AS customer_type_name' : ", '' AS customer_type_name";

        $summarySelect = '';
        $saleJoin = '';
        $purchaseJoin = '';
        $balanceJoin = '';

        if ($includeSummary) {
            $summarySelect = ',
    COALESCE(si.cnt, 0) AS total_sale_invoice,
    COALESCE(si.amt, 0) AS total_sale_invoice_amount,
    COALESCE(bal.balance_amount, 0) AS total_previous_balance_amount,
    COALESCE(pi.cnt, 0) AS total_purchase_invoice,
    COALESCE(pi.amt, 0) AS total_purchase_invoice_amount';

            $saleJoin = 'LEFT JOIN (
    SELECT 0 AS customer_id, 0 AS cnt, 0 AS amt WHERE 0
) si ON 1 = 0';
            if ($hasSale) {
                $saleJoin = "LEFT JOIN (
        SELECT customer_id,
               COUNT(*) AS cnt,
               COALESCE(SUM(grand_total), 0) AS amt
        FROM tbl_sale_invoices
        WHERE customer_id IS NOT NULL
          AND customer_id > 0
          AND LOWER(IFNULL(status, '')) NOT IN ('deleted')
        GROUP BY customer_id
    ) si ON si.customer_id = c.id";
            }

            $purchaseJoin = 'LEFT JOIN (
    SELECT 0 AS customer_id, 0 AS cnt, 0 AS amt WHERE 0
) pi ON 1 = 0';
            if ($hasPurchase) {
                $purchaseJoin = "LEFT JOIN (
        SELECT supplier_id AS customer_id,
               COUNT(*) AS cnt,
               COALESCE(SUM(grand_total), 0) AS amt
        FROM tbl_purchase_invoices
        WHERE supplier_id IS NOT NULL
          AND supplier_id > 0
          AND LOWER(IFNULL(status, '')) NOT IN ('deleted')
        GROUP BY supplier_id
    ) pi ON pi.customer_id = c.id";
            }

            $balanceJoin = 'LEFT JOIN (
    SELECT 0 AS customer_id, 0 AS balance_amount WHERE 0
) bal ON 1 = 0';
            if ($hasLedger) {
                $balanceJoin = "LEFT JOIN (
        SELECT cl.customer_id,
               cl.balance_amount AS balance_amount
        FROM tbl_customer_ledger cl
        INNER JOIN (
            SELECT customer_id, MAX(id) AS max_id
            FROM tbl_customer_ledger
            WHERE status = 1
              AND customer_id IS NOT NULL
              AND customer_id > 0
            GROUP BY customer_id
        ) latest ON latest.max_id = cl.id
    ) bal ON bal.customer_id = c.id";
            } elseif ($hasBalTbl) {
                $balanceJoin = 'LEFT JOIN tbl_customer_balance bal ON bal.customer_id = c.id';
            }
        }

        $where = ['IFNULL(c.status, 1) = 1'];
        if ($filterId > 0) {
            $where[] = 'c.id = ' . $filterId;
        }
        $whereSql = implode(' AND ', $where) . $typeWhere;

        return "
SELECT c.*{$selectExtra}{$summarySelect}
FROM tbl_customers c
{$typeJoin}
{$saleJoin}
{$purchaseJoin}
{$balanceJoin}
WHERE {$whereSql}
ORDER BY c.name ASC, c.id ASC
";
    }
}

if (!function_exists('auragold_api_customers_list_for_shop')) {
    /**
     * Fetch formatted customers from a connected shop DB.
     *
     * @param mysqli $link
     * @param array{id?:int,customer_id?:int,type?:string,include_summary?:bool} $filters
     * @return list<array<string,mixed>>
     */
    function auragold_api_customers_list_for_shop($link, array $filters = []): array
    {
        if (!auragold_api_customers_table_exists($link, 'tbl_customers')) {
            return [];
        }

        $sql  = auragold_api_customers_build_query($link, $filters);
        $rows = auragold_api_customers_fetch_all($link, $sql);
        $out  = [];
        foreach ($rows as $row) {
            $out[] = auragold_api_customers_format_row($row);
        }
        return $out;
    }
}

if (!function_exists('auragold_api_customers_resolve_access_token')) {
    /**
     * Read shop access_token from query string or headers.
     */
    function auragold_api_customers_resolve_access_token(): string
    {
        $token = trim((string) ($_GET['access_token'] ?? $_GET['shop_access_token'] ?? $_GET['token'] ?? ''));
        if ($token === '' && !empty($_SERVER['HTTP_X_ACCESS_TOKEN'])) {
            $token = trim((string) $_SERVER['HTTP_X_ACCESS_TOKEN']);
        }
        if ($token === '' && !empty($_SERVER['HTTP_AUTHORIZATION'])
            && preg_match('/^\s*Bearer\s+(\S+)\s*$/i', (string) $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $token = trim((string) $m[1]);
        }
        return $token;
    }
}

if (!function_exists('auragold_api_customers_all_shops_token_wise')) {
    /**
     * List all saved customers keyed by shop access_token.
     *
     * @param array{id?:int,customer_id?:int,type?:string,include_summary?:bool} $filters
     * @return array{ok:bool,count:int,data:array<string,array>,message?:string}
     */
    function auragold_api_customers_all_shops_token_wise(array $filters = []): array
    {
        if (!function_exists('auragold_api_connect_clone_source')) {
            return ['ok' => false, 'count' => 0, 'data' => [], 'message' => 'API shop connection helper missing.'];
        }

        $meta = auragold_api_connect_clone_source();
        if (!$meta) {
            return ['ok' => false, 'count' => 0, 'data' => [], 'message' => 'Could not connect to shop registry database.'];
        }

        if (function_exists('auragold_ensure_tbl_branches_access_token_column')) {
            auragold_ensure_tbl_branches_access_token_column($meta);
        }

        $rs = mysqli_query($meta, 'SELECT * FROM tbl_branches WHERE main_branch_id = 0 ORDER BY id ASC');
        if (!$rs) {
            $err = mysqli_error($meta);
            auragold_api_close_meta_link($meta);
            return ['ok' => false, 'count' => 0, 'data' => [], 'message' => 'Query failed' . ($err !== '' ? ': ' . $err : '.')];
        }

        $data = [];
        while ($row = mysqli_fetch_assoc($rs)) {
            $shopId = (int) ($row['id'] ?? 0);
            $token  = trim((string) ($row['access_token'] ?? ''));
            if ($token === '' && $shopId > 0 && function_exists('auragold_ensure_shop_access_token')) {
                $token = auragold_ensure_shop_access_token($meta, $shopId);
            }
            if ($token === '') {
                continue;
            }

            $connShop = auragold_api_connect_shop_database_from_branch_row($row);
            if (empty($connShop['ok']) || !($connShop['link'] instanceof mysqli)) {
                $data[$token] = [
                    'shop_id'      => $shopId,
                    'shop_name'    => (string) ($row['name'] ?? ''),
                    'access_token' => $token,
                    'count'        => 0,
                    'customers'    => [],
                    'error'        => (string) ($connShop['message'] ?? 'Shop database unavailable.'),
                ];
                continue;
            }

            /** @var mysqli $link */
            $link      = $connShop['link'];
            $customers = auragold_api_customers_list_for_shop($link, $filters);
            mysqli_close($link);

            $data[$token] = [
                'shop_id'      => $shopId,
                'shop_name'    => (string) ($row['name'] ?? ''),
                'access_token' => $token,
                'count'        => count($customers),
                'customers'    => $customers,
            ];
        }
        mysqli_free_result($rs);
        auragold_api_close_meta_link($meta);

        return [
            'ok'    => true,
            'count' => count($data),
            'data'  => $data,
        ];
    }
}
