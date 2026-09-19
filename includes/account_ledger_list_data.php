<?php
/**
 * Shared Account Ledger list data (account-ledger.php + export/import).
 */

if (!function_exists('auragold_account_ledger_group_map')) {
    function auragold_account_ledger_group_map(): array
    {
        return [
            'Cash' => 'Cash-in Hand',
            'Bank Account' => 'Bank Accounts',
            'Sales Account' => 'Sales',
            'Purchase Account' => 'Purchase',
            'Making Sale Account' => 'Sales',
            'Making Sales Account' => 'Sales',
            'Making Purchase Account' => 'Purchase',
            'Hedging Account' => 'Primary',
            'Manufacturing Account' => 'Primary',
            'Metal Exchange' => 'Primary',
            'Profit And Loss' => 'Primary',
            'Advance Payment' => 'Loans & Advances(Asset)',
            'Salary' => 'Indirect Expenses',
            'Service Account' => 'Service Account',
            'PDC Payable' => 'Current Liabilities',
            'PDC Receivable' => 'Current Assets',
            'Discount Allowed' => 'Indirect Expenses',
            'Discount Received' => 'Indirect Income',
            'Coupon Discount Allowed' => 'Indirect Expenses',
            'Coupon Discount Received' => 'Indirect Income',
            'RoundOFF Allowed' => 'Indirect Expenses',
            'RoundOFF Received' => 'Indirect Income',
            'Adjust Price_Discount' => 'Indirect Expenses',
            'Redeem Allowed' => 'Indirect Expenses',
        ];
    }
}

if (!function_exists('auragold_account_ledger_resolve_filters')) {
    /**
     * @return array{filter_group:string,filter_search:string,filter_branch:int,branch_filter_explicit:bool,pagination_branch_extra:array<string,int>}
     */
    function auragold_account_ledger_resolve_filters($conn, array $input = []): array
    {
        $filter_group   = isset($input['group']) ? esc((string) $input['group']) : '';
        $filter_search  = isset($input['search']) ? esc((string) $input['search']) : '';
        $branch_filter_explicit = array_key_exists('branch_id', $input);
        $filter_branch  = isset($input['branch_id']) ? (int) $input['branch_id'] : 0;

        if (!$branch_filter_explicit && function_exists('auragold_effective_branch_id')) {
            $effb = (int) auragold_effective_branch_id();
            if ($effb > 0) {
                $filter_branch = $effb;
            } else {
                $rchk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'branch_id'");
                $ledger_has_branch = $rchk && mysqli_num_rows($rchk) > 0;
                if ($rchk) {
                    mysqli_free_result($rchk);
                }
                if ($ledger_has_branch && function_exists('auragold_settings_main_branch_id')) {
                    $mid = (int) auragold_settings_main_branch_id();
                    if ($mid > 0) {
                        $filter_branch = $mid;
                    }
                }
            }
        }

        $pagination_branch_extra = [];
        if (!$branch_filter_explicit && $filter_branch > 0) {
            $pagination_branch_extra['branch_id'] = $filter_branch;
        }

        return [
            'filter_group'              => $filter_group,
            'filter_search'             => $filter_search,
            'filter_branch'             => $filter_branch,
            'branch_filter_explicit'    => $branch_filter_explicit,
            'pagination_branch_extra'   => $pagination_branch_extra,
        ];
    }
}

if (!function_exists('auragold_account_ledger_fetch_rows')) {
    /**
     * @return array{rows:list<array<string,mixed>>,grand_total:float,ledger_has_branch:bool,branch_id_to_label:array<int,string>,filters:array<string,mixed>}
     */
    function auragold_account_ledger_fetch_rows($conn, array $input = []): array
    {
        require_once __DIR__ . '/ensure_customer_ledger_branch_column.php';
        require_once __DIR__ . '/auragold_ensure_ledger_customer.php';
        require_once __DIR__ . '/auragold_sundry_debtors_options.php';

        auragold_ensure_customer_ledger_branch_column($conn);

        $filters = auragold_account_ledger_resolve_filters($conn, $input);
        $filter_group  = $filters['filter_group'];
        $filter_search = $filters['filter_search'];
        $filter_branch = $filters['filter_branch'];

        $ledger_has_branch = false;
        $rchk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'branch_id'");
        if ($rchk && mysqli_num_rows($rchk) > 0) {
            $ledger_has_branch = true;
        }
        if ($rchk) {
            mysqli_free_result($rchk);
        }

        $account_ledger_branches = function_exists('auragold_filter_branches_for_login_scope')
            ? auragold_filter_branches_for_login_scope()
            : (function_exists('auragold_registry_active_branches_list')
                ? auragold_registry_active_branches_list()
                : []);
        if (empty($account_ledger_branches) && function_exists('getListMaster')) {
            $tree_root = function_exists('auragold_branch_stock_transfer_tree_root_id')
                ? (int) auragold_branch_stock_transfer_tree_root_id()
                : (function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0);
            if ($tree_root > 0) {
                $account_ledger_branches = @getListMaster(
                    'SELECT id, name, IFNULL(main_branch_id, 0) AS main_branch_id FROM tbl_branches WHERE status = 1 AND (id = '
                    . $tree_root . ' OR IFNULL(main_branch_id, 0) = ' . $tree_root . ') ORDER BY IFNULL(main_branch_id, 0) ASC, name ASC'
                );
            } else {
                $account_ledger_branches = @getListMaster(
                    'SELECT id, name, IFNULL(main_branch_id, 0) AS main_branch_id FROM tbl_branches WHERE status = 1 ORDER BY name ASC'
                );
            }
        }
        if (!is_array($account_ledger_branches)) {
            $account_ledger_branches = [];
        }

        // Ensure main_branch_id is present for Main / Sub labels in the filter dropdown.
        $tree_root_for_label = function_exists('auragold_branch_stock_transfer_tree_root_id')
            ? (int) auragold_branch_stock_transfer_tree_root_id()
            : (function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0);
        $branch_id_to_label = [];
        foreach ($account_ledger_branches as &$abr) {
            if (!is_array($abr)) {
                continue;
            }
            $aid = (int) ($abr['id'] ?? 0);
            if ($aid <= 0) {
                continue;
            }
            $mb = array_key_exists('main_branch_id', $abr) ? (int) $abr['main_branch_id'] : -1;
            if ($mb < 0 && function_exists('getRecordMaster')) {
                $brRow = @getRecordMaster(
                    'SELECT IFNULL(main_branch_id, 0) AS mb FROM tbl_branches WHERE id = ' . $aid . ' LIMIT 1'
                );
                $mb = $brRow ? (int) ($brRow['mb'] ?? 0) : 0;
                $abr['main_branch_id'] = $mb;
            }
            $is_main = ($mb === 0) || ($tree_root_for_label > 0 && $aid === $tree_root_for_label);
            $base_name = trim((string) ($abr['name'] ?? ''));
            if ($base_name === '') {
                $base_name = 'Branch #' . $aid;
            }
            $abr['name'] = $base_name . ($is_main ? ' (Main)' : ' (Sub)');
            $abr['is_main'] = $is_main ? 1 : 0;
            $branch_id_to_label[$aid] = $base_name;
        }
        unset($abr);

        // Main first, then subs alphabetically.
        usort($account_ledger_branches, static function ($a, $b) {
            $am = (int) ($a['is_main'] ?? 0);
            $bm = (int) ($b['is_main'] ?? 0);
            if ($am !== $bm) {
                return $bm <=> $am;
            }
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $name_ci = static function (string $expr): string {
            return 'CONVERT(' . $expr . ' USING utf8mb4) COLLATE utf8mb4_unicode_ci';
        };
        $ledger_name_ci = $name_ci('customer_name');
        $customer_name_ci = $name_ci('TRIM(name)');
        $ob_name_ci = $name_ci('ob.customer_name');
        $z_name_ci = $name_ci('z.customer_name');
        $cl_name_ci = $name_ci('cl.customer_name');

        $ledger_names_sql = "
                SELECT DISTINCT {$ledger_name_ci} AS ledger_name
                FROM tbl_customer_ledger
                WHERE status = 1 AND TRIM(customer_name) <> ''
                UNION
                SELECT DISTINCT {$customer_name_ci} AS ledger_name
                FROM tbl_customers
                WHERE status = 1 AND TRIM(name) <> ''
        ";

        $where = "l.status = 1";
        if ($filter_search !== '') {
            $where .= " AND l.customer_name LIKE '%" . $filter_search . "%'";
        }

        if ($ledger_has_branch) {
            $main_bid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
            $fb = (int) $filter_branch;
            if ($fb > 0) {
                if ($main_bid > 0 && $fb === $main_bid) {
                    $branch_opening_match = '(z.branch_id IS NULL OR z.branch_id = 0 OR z.branch_id = ' . $fb . ')';
                } else {
                    $branch_opening_match = 'COALESCE(z.branch_id, 0) = ' . $fb;
                }
                $ledgers_sql = "
            SELECT dn.ledger_name AS ledger_name,
                   COALESCE(ob.balance_amount, 0) AS opening_balance,
                   COALESCE(b.name, '') AS branch_disp_name
            FROM (
                {$ledger_names_sql}
            ) dn
            LEFT JOIN tbl_customer_ledger ob ON {$ob_name_ci} = dn.ledger_name
                AND ob.status = 1
                AND ob.transaction_type = 'opening'
                AND ob.id = (
                    SELECT MAX(z.id)
                    FROM tbl_customer_ledger z
                    WHERE {$z_name_ci} = dn.ledger_name
                      AND z.status = 1
                      AND z.transaction_type = 'opening'
                      AND " . $branch_opening_match . "
                )
            LEFT JOIN tbl_branches b ON b.id = ob.branch_id
            WHERE 1=1
        ";
                if ($filter_search !== '') {
                    $ledgers_sql .= " AND dn.ledger_name LIKE '%" . $filter_search . "%'";
                }
                $ledgers_sql .= ' ORDER BY dn.ledger_name ASC';
                $all_ledgers = getList($ledgers_sql);
            } else {
                $w_open_search = '';
                if ($filter_search !== '') {
                    $w_open_search = " AND dn.ledger_name LIKE '%" . $filter_search . "%'";
                }
                $ledgers_sql = "
            SELECT dn.ledger_name AS ledger_name,
                   COALESCE(ob.balance_amount, 0) AS opening_balance,
                   COALESCE(b.name, '') AS branch_disp_name
            FROM (
                {$ledger_names_sql}
            ) dn
            LEFT JOIN tbl_customer_ledger ob ON {$ob_name_ci} = dn.ledger_name
                AND ob.status = 1
                AND ob.transaction_type = 'opening'
                AND ob.id = (
                    SELECT MAX(z.id)
                    FROM tbl_customer_ledger z
                    WHERE {$z_name_ci} = dn.ledger_name
                      AND z.status = 1
                      AND z.transaction_type = 'opening'
                )
            LEFT JOIN tbl_branches b ON b.id = ob.branch_id
            WHERE 1=1 {$w_open_search}
            ORDER BY dn.ledger_name ASC
        ";
                $all_ledgers = getList($ledgers_sql);
            }
        } else {
            $ledgers_sql = "
        SELECT dn.ledger_name AS ledger_name,
               (SELECT cl.balance_amount FROM tbl_customer_ledger cl
                WHERE {$cl_name_ci} = dn.ledger_name AND cl.status = 1 AND cl.transaction_type = 'opening'
                ORDER BY cl.transaction_date DESC, cl.id DESC LIMIT 1) AS opening_balance
        FROM (
            {$ledger_names_sql}
        ) dn
        WHERE 1=1
    ";
            if ($filter_search !== '') {
                $ledgers_sql .= " AND dn.ledger_name LIKE '%" . $filter_search . "%'";
            }
            $ledgers_sql .= ' ORDER BY dn.ledger_name ASC';
            $all_ledgers = getList($ledgers_sql);
        }

        if (!is_array($all_ledgers)) {
            $all_ledgers = [];
        }

        $ledger_group_map = auragold_account_ledger_group_map();
        $ledger_rows = [];

        foreach ($all_ledgers as $r) {
            $name = trim((string) ($r['ledger_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $ob = (float) ($r['opening_balance'] ?? 0);
            $crdr = $ob >= 0 ? 'Dr' : 'Cr';
            $ob_abs = abs($ob);
            $customer_id = 0;
            $is_fixed = 0;
            $name_esc = mysqli_real_escape_string($conn, trim((string) $name));
            if (!function_exists('auragold_ensure_customer_is_fixed_column')) {
                require_once __DIR__ . '/account_ledger_fixed.php';
            }
            auragold_ensure_customer_is_fixed_column($conn);
            $cust = getRecord("SELECT id, mobile_no, COALESCE(is_fixed, 0) AS is_fixed, COALESCE(sundry_debtors_id, 0) AS sundry_debtors_id FROM tbl_customers WHERE TRIM(name) = '" . $name_esc . "' AND status = 1 LIMIT 1");
            if ($cust) {
                $customer_id = (int) $cust['id'];
                $is_fixed = (int) ($cust['is_fixed'] ?? 0);
            } elseif (function_exists('auragold_ensure_ledger_customer')) {
                $ensured = auragold_ensure_ledger_customer($conn, $name, $filter_branch > 0 ? $filter_branch : 0);
                if (!empty($ensured['ok']) && (int) ($ensured['id'] ?? 0) > 0) {
                    $customer_id = (int) $ensured['id'];
                    $cust2 = getRecord('SELECT COALESCE(is_fixed, 0) AS is_fixed, COALESCE(sundry_debtors_id, 0) AS sundry_debtors_id FROM tbl_customers WHERE id = ' . $customer_id . ' LIMIT 1');
                    $is_fixed = is_array($cust2) ? (int) ($cust2['is_fixed'] ?? 0) : 0;
                    if (is_array($cust2)) {
                        $cust = $cust2;
                    }
                }
            }

            $group_name = isset($ledger_group_map[$name]) ? $ledger_group_map[$name] : 'Primary';
            if (!isset($ledger_group_map[$name]) && is_array($cust)) {
                $sdName = auragold_sundry_debtors_name_by_id((int) ($cust['sundry_debtors_id'] ?? 0));
                if ($sdName !== '') {
                    $group_name = $sdName;
                }
            }
            if ($filter_group !== '' && $group_name !== $filter_group) {
                continue;
            }
            if ($is_fixed !== 1 && function_exists('auragold_account_ledger_is_fixed_name') && auragold_account_ledger_is_fixed_name((string) $name)) {
                $is_fixed = 1;
                if ($customer_id > 0) {
                    @mysqli_query($conn, 'UPDATE tbl_customers SET is_fixed = 1 WHERE id = ' . (int) $customer_id);
                }
            }

            $contact = is_array($cust) ? trim((string) ($cust['mobile_no'] ?? '')) : '';

            $branch_disp = 'Main Branch';
            if ($ledger_has_branch) {
                $bn = trim((string) ($r['branch_disp_name'] ?? ''));
                if ($filter_branch > 0) {
                    $branch_disp = $branch_id_to_label[$filter_branch] ?? ($bn !== '' ? $bn : '—');
                } else {
                    $branch_disp = $bn !== '' ? $bn : '—';
                }
            }

            $ledger_rows[] = [
                'ledger_name'     => $name,
                'contact'         => $contact,
                'group_name'      => $group_name,
                'branch_name'     => $branch_disp,
                'opening_balance' => $ob_abs,
                'crdr'            => $crdr,
                'customer_id'     => $customer_id,
                'is_fixed'        => $is_fixed ? 1 : 0,
            ];
        }

        $grand_total = 0.0;
        foreach ($ledger_rows as $row) {
            $grand_total += ($row['crdr'] === 'Dr' ? $row['opening_balance'] : -$row['opening_balance']);
        }

        return [
            'rows'                => $ledger_rows,
            'grand_total'         => $grand_total,
            'ledger_has_branch'   => $ledger_has_branch,
            'branch_id_to_label'  => $branch_id_to_label,
            'filters'             => $filters,
            'account_ledger_branches' => $account_ledger_branches,
        ];
    }
}
