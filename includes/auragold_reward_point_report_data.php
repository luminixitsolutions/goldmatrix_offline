<?php

declare(strict_types=1);

require_once __DIR__ . '/auragold_reward_point_settings.php';

if (!function_exists('auragold_reward_point_report_fetch')) {
    /**
     * Sale + POS reward rows for the Reward Point Report (pagination supported).
     *
     * @return array{
     *   total:int,
     *   rows: list<array<string,mixed>>,
     *   sort:string,
     *   order:'asc'|'desc',
     *   from_date:string,
     *   to_date:string,
     *   error:?string
     * }
     */
    function auragold_reward_point_report_fetch($conn, array $params): array
    {
        $from_date = isset($params['from_date']) ? trim((string) $params['from_date']) : '';
        $to_date = isset($params['to_date']) ? trim((string) $params['to_date']) : '';
        $search = isset($params['search']) ? trim((string) esc($params['search'])) : '';

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
            $from_date = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
            $to_date = date('Y-m-t');
        }

        $sort = isset($params['sort']) ? preg_replace('/[^a-z0-9_]/', '', (string) $params['sort']) : 'invoice_date';
        $orderStr = isset($params['order']) ? strtolower(trim((string) $params['order'])) : 'asc';
        $order = $orderStr === 'desc' ? 'DESC' : 'ASC';

        $sort_map = [
            'customer_name'    => 'customer_name',
            'invoice_no'       => 'invoice_no',
            'invoice_date'     => 'invoice_date',
            'generated_point'  => 'reward_points',
            'redeemed_point'   => 'redeem_points',
            'redeem_value'     => 'redeem_value',
            'account_no'       => 'account_no',
        ];
        if ($sort === '' || !isset($sort_map[$sort])) {
            $sort = 'invoice_date';
        }
        $order_col = $sort_map[$sort];

        $limit = isset($params['limit']) ? (int) $params['limit'] : 25;
        if ($limit < 1) {
            $limit = 25;
        }
        $offset = isset($params['offset']) ? max(0, (int) $params['offset']) : 0;
        $unlimited = !empty($params['unlimited']);
        $maxCap = isset($params['max_limit_cap']) ? (int) $params['max_limit_cap'] : 0;
        if ($unlimited) {
            $offset = 0;
        }

        $scope_branch_id = isset($params['branch_id']) ? (int) $params['branch_id'] : 0;
        if (function_exists('auragold_resolve_branch_id_for_session')) {
            $scope_branch_id = (int) auragold_resolve_branch_id_for_session($scope_branch_id);
        }
        $main_bid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($scope_branch_id <= 0 && function_exists('auragold_effective_branch_id')) {
            $scope_branch_id = (int) auragold_effective_branch_id();
        }
        if ($scope_branch_id <= 0 && $main_bid > 0) {
            $scope_branch_id = $main_bid;
        }

        $si_has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'branch_id');
        $brSiAnd = '';
        if ($si_has_branch && $scope_branch_id > 0) {
            if ($main_bid > 0 && $scope_branch_id === $main_bid) {
                $brSiAnd = ' AND (si.branch_id = ' . (int) $scope_branch_id . ' OR si.branch_id IS NULL OR si.branch_id = 0)';
            } else {
                $brSiAnd = ' AND COALESCE(si.branch_id, 0) = ' . (int) $scope_branch_id;
            }
        }

        $psi_has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_pos_sale_invoices', 'branch_id');
        $brPsiAnd = '';
        if ($psi_has_branch && $scope_branch_id > 0) {
            if ($main_bid > 0 && $scope_branch_id === $main_bid) {
                $brPsiAnd = ' AND (psi.branch_id = ' . (int) $scope_branch_id . ' OR psi.branch_id IS NULL OR psi.branch_id = 0)';
            } else {
                $brPsiAnd = ' AND COALESCE(psi.branch_id, 0) = ' . (int) $scope_branch_id;
            }
        }

        $mainBidSql = $main_bid > 0 ? (int) $main_bid : 0;
        $onePtExpr = "CAST(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(rps.settings_json, '$.blocks._all.one_pt_value')), '0') AS DECIMAL(18,6))";

        auragold_ensure_reward_point_settings_table($conn);

        $searchSql = '';
        if ($search !== '') {
            $s = mysqli_real_escape_string($conn, $search);
            $searchSql = " AND (
                si.customer_name LIKE '%$s%'
                OR si.invoice_no LIKE '%$s%'
                OR CAST(si.customer_id AS CHAR) LIKE '%$s%'
                OR COALESCE(NULLIF(TRIM(c.bank_account_no), ''), '') LIKE '%$s%'
            )";
        }

        $searchSqlPos = '';
        if ($search !== '') {
            $s = mysqli_real_escape_string($conn, $search);
            $searchSqlPos = " AND (
                COALESCE(NULLIF(TRIM(psi.customer_name),''), COALESCE(c2.name, '')) LIKE '%$s%'
                OR psi.invoice_no LIKE '%$s%'
                OR CAST(psi.customer_id AS CHAR) LIKE '%$s%'
                OR COALESCE(NULLIF(TRIM(c2.bank_account_no), ''), '') LIKE '%$s%'
            )";
        }

        $has_sale = true;
        $chk_si = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_invoices'");
        if (!$chk_si || mysqli_num_rows($chk_si) === 0) {
            $has_sale = false;
        }
        if ($chk_si) {
            mysqli_free_result($chk_si);
        }
        $has_pos = true;
        $chk_psi = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_pos_sale_invoices'");
        if (!$chk_psi || mysqli_num_rows($chk_psi) === 0) {
            $has_pos = false;
        }
        if ($chk_psi) {
            mysqli_free_result($chk_psi);
        }

        $reward_sale = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'reward_points');
        $reward_pos = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_pos_sale_invoices', 'reward_points');

        try {
            $union_parts = [];

            if ($has_sale && $reward_sale) {
                $branchJoinSale = 'COALESCE(NULLIF(si.branch_id, 0), ' . ($mainBidSql > 0 ? $mainBidSql : '0') . ')';
                $union_parts[] = "
                    SELECT
                        'sale' AS src,
                        si.id AS invoice_id,
                        si.invoice_no AS invoice_no,
                        si.invoice_date AS invoice_date,
                        si.customer_name AS customer_name,
                        COALESCE(NULLIF(TRIM(c.bank_account_no), ''), CAST(si.customer_id AS CHAR)) AS account_no,
                        COALESCE(si.reward_points, 0) AS reward_points,
                        COALESCE(si.redeem_points, 0) AS redeem_points,
                        (COALESCE(si.redeem_points, 0) * COALESCE($onePtExpr, 0)) AS redeem_value
                    FROM tbl_sale_invoices si
                    LEFT JOIN tbl_customers c ON c.id = si.customer_id AND c.status = 1
                    LEFT JOIN tbl_auragold_reward_point_settings rps ON rps.branch_id = $branchJoinSale
                    WHERE si.invoice_date >= '$from_date'
                      AND si.invoice_date <= '$to_date'
                      $brSiAnd
                      $searchSql
                ";
            }

            if ($has_pos && $reward_pos) {
                $branchJoinPos = 'COALESCE(NULLIF(psi.branch_id, 0), ' . ($mainBidSql > 0 ? $mainBidSql : '0') . ')';
                $union_parts[] = "
                    SELECT
                        'pos' AS src,
                        psi.id AS invoice_id,
                        psi.invoice_no AS invoice_no,
                        psi.invoice_date AS invoice_date,
                        COALESCE(NULLIF(TRIM(psi.customer_name),''), COALESCE(c2.name, '')) AS customer_name,
                        COALESCE(NULLIF(TRIM(c2.bank_account_no), ''), CAST(psi.customer_id AS CHAR)) AS account_no,
                        COALESCE(psi.reward_points, 0) AS reward_points,
                        COALESCE(psi.redeem_points, 0) AS redeem_points,
                        (COALESCE(psi.redeem_points, 0) * COALESCE($onePtExpr, 0)) AS redeem_value
                    FROM tbl_pos_sale_invoices psi
                    LEFT JOIN tbl_customers c2 ON c2.id = psi.customer_id AND c2.status = 1
                    LEFT JOIN tbl_auragold_reward_point_settings rps ON rps.branch_id = $branchJoinPos
                    WHERE psi.invoice_date >= '$from_date'
                      AND psi.invoice_date <= '$to_date'
                      $brPsiAnd
                      $searchSqlPos
                ";
            }

            if ($union_parts === []) {
                return [
                    'total'     => 0,
                    'rows'      => [],
                    'sort'      => $sort,
                    'order'     => $order === 'DESC' ? 'desc' : 'asc',
                    'from_date' => $from_date,
                    'to_date'   => $to_date,
                    'error'     => null,
                ];
            }

            $union_sql = implode(' UNION ALL ', $union_parts);
            $base_from = "FROM ($union_sql) u";

            $count_row = getRecord("SELECT COUNT(*) AS c $base_from");
            $total = (int) ($count_row['c'] ?? 0);

            $limit_sql = '';
            if ($unlimited) {
                if ($maxCap > 0) {
                    $limit_sql = ' LIMIT ' . $maxCap;
                }
            } else {
                $limit_sql = ' LIMIT ' . $limit . ' OFFSET ' . $offset;
            }

            $data = getList("SELECT u.* $base_from ORDER BY u.$order_col $order $limit_sql");

            $out = [];
            foreach ($data as $row) {
                $rp = (float) ($row['reward_points'] ?? 0);
                $rdp = (float) ($row['redeem_points'] ?? 0);
                $rv = (float) ($row['redeem_value'] ?? 0);
                $out[] = [
                    'src'              => (string) ($row['src'] ?? ''),
                    'invoice_id'       => (int) ($row['invoice_id'] ?? 0),
                    'customer_name'    => (string) ($row['customer_name'] ?? ''),
                    'invoice_no'       => (string) ($row['invoice_no'] ?? ''),
                    'invoice_date'     => (string) ($row['invoice_date'] ?? ''),
                    'generated_point'  => round($rp, 4),
                    'redeemed_point'   => round($rdp, 4),
                    'redeem_value'     => round($rv, 2),
                    'account_no'       => (string) ($row['account_no'] ?? ''),
                ];
            }

            return [
                'total'     => $total,
                'rows'      => $out,
                'sort'      => $sort,
                'order'     => $order === 'DESC' ? 'desc' : 'asc',
                'from_date' => $from_date,
                'to_date'   => $to_date,
                'error'     => null,
            ];
        } catch (Throwable $e) {
            return [
                'total'     => 0,
                'rows'      => [],
                'sort'      => $sort,
                'order'     => $order === 'DESC' ? 'desc' : 'asc',
                'from_date' => $from_date,
                'to_date'   => $to_date,
                'error'     => $e->getMessage(),
            ];
        }
    }
}
