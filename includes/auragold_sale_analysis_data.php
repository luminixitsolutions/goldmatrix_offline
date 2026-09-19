<?php

/**
 * Sale Analysis grid — sale invoice lines mapped to sale-analysis.php column keys.
 */

declare(strict_types=1);

if (!function_exists('auragold_sale_analysis_parse_range')) {
    /**
     * @return array{from_dmY:string,to_dmY:string,from_ymd:string,to_ymd:string,label:string}
     */
    function auragold_sale_analysis_parse_range(string $range): array {
        $range = trim($range);
        $parts = preg_split('/\s+\-\s+/', $range, 2);
        if (count($parts) !== 2) {
            $today = new DateTimeImmutable('today');
            $y = (int) $today->format('Y');
            $m = (int) $today->format('n');
            $fyStart = $m >= 4 ? $y : ($y - 1);
            $parts = ['01-04-' . $fyStart, '31-03-' . ($fyStart + 1)];
        }
        $d1 = DateTimeImmutable::createFromFormat('d-m-Y', trim($parts[0]));
        $d2 = DateTimeImmutable::createFromFormat('d-m-Y', trim($parts[1]));
        if (!$d1 || !$d2) {
            $d1 = new DateTimeImmutable('-90 days');
            $d2 = new DateTimeImmutable('today');
        }
        if ($d1 > $d2) {
            [$d1, $d2] = [$d2, $d1];
        }

        return [
            'from_dmY' => $d1->format('d-m-Y'),
            'to_dmY'   => $d2->format('d-m-Y'),
            'from_ymd' => $d1->format('Y-m-d'),
            'to_ymd'   => $d2->format('Y-m-d'),
            'label'    => $d1->format('d-m-Y') . ' - ' . $d2->format('d-m-Y'),
        ];
    }
}

if (!function_exists('auragold_sale_analysis_format_pay_types')) {
    function auragold_sale_analysis_format_pay_types(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        $chunks = preg_split('/\s+\+\s+/', $raw) ?: [];
        $out = [];
        foreach ($chunks as $c) {
            $c = trim((string) $c);
            if ($c === '') {
                continue;
            }
            $out[] = ucwords(str_replace('-', ' ', strtolower($c)));
        }

        return implode(' + ', $out);
    }
}

if (!function_exists('auragold_sale_analysis_pay_subquery_sql')) {
    /**
     * Payment aggregates per document — uses COALESCE(amount, current_order_amount) like payment grids.
     * $fkCol is invoice_id for sale/POS invoices, quotation_id for sale quotations (aliased as invoice_id).
     */
    function auragold_sale_analysis_pay_subquery_sql(string $payTable, string $fkCol = 'invoice_id'): string {
        $t = preg_replace('/[^a-zA-Z0-9_]/', '', $payTable);
        $fk = preg_replace('/[^a-zA-Z0-9_]/', '', $fkCol);
        if ($fk === '') {
            $fk = 'invoice_id';
        }

        return "
            SELECT
                `{$fk}` AS invoice_id,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) = 'cash'
                    THEN COALESCE(NULLIF(amount, 0), current_order_amount, 0) ELSE 0 END) AS pay_cash,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) = 'bank'
                    THEN COALESCE(NULLIF(amount, 0), current_order_amount, 0) ELSE 0 END) AS pay_bank,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) IN ('cheque', 'check')
                    THEN COALESCE(NULLIF(amount, 0), current_order_amount, 0) ELSE 0 END) AS pay_cheque,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) = 'upi'
                    THEN COALESCE(NULLIF(amount, 0), current_order_amount, 0) ELSE 0 END) AS pay_upi,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) = 'card'
                    THEN COALESCE(NULLIF(amount, 0), current_order_amount, 0) ELSE 0 END) AS pay_card,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) = 'metal-exchange'
                    THEN COALESCE(NULLIF(amount, 0), current_order_amount, 0) ELSE 0 END) AS pay_mex_amt,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) = 'metal-exchange'
                    THEN COALESCE(quantity, 0) ELSE 0 END) AS pay_mex_wt,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) = 'scrap'
                    THEN COALESCE(NULLIF(amount, 0), current_order_amount, 0) ELSE 0 END) AS pay_scrap_amt,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) = 'scrap'
                    THEN COALESCE(quantity, 0) ELSE 0 END) AS pay_scrap_wt,
                SUM(CASE
                    WHEN LOWER(TRIM(payment_type)) LIKE '%fund%transfer%'
                         OR LOWER(TRIM(payment_type)) = 'fund-transfer'
                    THEN COALESCE(NULLIF(amount, 0), current_order_amount, 0)
                    ELSE 0 END) AS pay_fund,
                SUM(CASE WHEN LOWER(TRIM(payment_type)) LIKE '%advance%'
                    THEN COALESCE(NULLIF(amount, 0), current_order_amount, 0)
                    ELSE 0 END) AS pay_adv,
                GROUP_CONCAT(DISTINCT payment_type ORDER BY payment_type SEPARATOR ' + ') AS pay_types
            FROM `{$t}`
            WHERE IFNULL(status, 1) = 1
            GROUP BY `{$fk}`
        ";
    }
}

if (!function_exists('auragold_sale_analysis_fetch_rows')) {
    /**
     * Load sale document line rows for the given inclusive date range (Y-m-d).
     * Sources: sale invoice, POS sale invoice, sale quotation.
     * Includes draft bills; excludes cancelled/void/deleted only.
     * Separate queries merged in PHP avoids UNION collation mismatches across tables.
     *
     * @return list<array<string, string>>
     */
    function auragold_sale_analysis_fetch_rows($conn, string $from_ymd, string $to_ymd): array {
        if (!$conn instanceof mysqli) {
            return [];
        }
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_invoices'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }

            return [];
        }
        mysqli_free_result($chk);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_ymd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_ymd)) {
            return [];
        }

        $from_e = esc($from_ymd);
        $to_e = esc($to_ymd);

        $sources = [];

        /** Standard Sales Invoice */
        $branch_join_si = '';
        $branch_sel_si = '\'\' AS branch_display_name';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'branch_id')) {
            $branch_join_si = 'LEFT JOIN tbl_branches br ON br.id = si.branch_id';
            $branch_sel_si = 'COALESCE(br.name, \'\') AS branch_display_name';
        }

        $huid_sel = '\'-\' AS line_huid';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_invoice_items', 'huid_no')) {
            $huid_sel = 'IFNULL(NULLIF(TRIM(sii.huid_no), \'\'), \'—\') AS line_huid';
        }

        $prev_bal_sel = '0 AS prev_bal_used';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'previous_balance_used_amt')) {
            $prev_bal_sel = 'COALESCE(si.previous_balance_used_amt, 0) AS prev_bal_used';
        }

        $branch_where_si = '';
        if (function_exists('auragold_sale_invoices_branch_where_sql')) {
            $branch_where_si = auragold_sale_invoices_branch_where_sql($conn, 'si');
        }

        $pay_si = auragold_sale_analysis_pay_subquery_sql('tbl_sale_invoice_payments');

        $branch_id_sel_si = '0 AS branch_id_num';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'branch_id')) {
            $branch_id_sel_si = 'COALESCE(si.branch_id, 0) AS branch_id_num';
        }
        $carat_sel_si = '\'\' AS line_carat';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_invoice_items', 'carat')) {
            $carat_sel_si = 'IFNULL(sii.carat, \'\') AS line_carat';
        }

        $sources[] = "
            SELECT
                sii.id AS line_id,
                si.invoice_no,
                DATE_FORMAT(si.invoice_date, '%Y-%m-%d') AS inv_sort_iso,
                si.customer_name AS party_name,
                si.sales_person,
                DATE_FORMAT(si.invoice_date, '%d-%m-%Y') AS inv_dt_dmY,
                si.against_of,
                si.currency,
                si.discount_amt,
                si.advance_payment,
                si.balance_amt,
                si.round_off,
                si.comment AS si_comment,
                si.additional_amt,
                si.grand_total AS si_grand_total,
                si.group_name,
                'Sale Invoice' AS voucher_label,
                COALESCE(si.layaways_id, 0) AS layaways_id_num,
                {$prev_bal_sel},
                sii.barcode,
                sii.quantity AS pcs_qty,
                sii.product_name,
                sii.gross_weight,
                sii.final_weight,
                COALESCE(sii.metal_value, 0) AS metal_value_num,
                sii.making_amount AS making_amt_num,
                sii.amount AS amount_num,
                sii.net_amount AS net_amount_num,
                sii.tax_amount AS tax_amount_num,
                sii.net_amt_with_tax AS line_grand_num,
                COALESCE(cat.name, '') AS category_name,
                COALESCE(p.article, '') AS product_article,
                COALESCE(m.display_name, '') AS metal_display_name,
                {$branch_id_sel_si},
                COALESCE(pc.metal_id, 0) AS metal_id_num,
                COALESCE(sii.product_id, 0) AS product_id_num,
                COALESCE(p.category_id, 0) AS category_id_num,
                {$carat_sel_si},
                {$branch_sel_si},
                {$huid_sel},
                COALESCE(NULLIF(TRIM(cust.national_id), ''), NULLIF(TRIM(cust.identity_no), ''), '') AS cust_nid,
                COALESCE(
                    NULLIF(TRIM(cust.mobile_no), ''),
                    NULLIF(TRIM(cust.phone_no), ''),
                    ''
                ) AS cust_mobile,
                COALESCE(pay.pay_cash, 0) AS pay_cash,
                COALESCE(pay.pay_bank, 0) AS pay_bank,
                COALESCE(pay.pay_cheque, 0) AS pay_cheque,
                COALESCE(pay.pay_upi, 0) AS pay_upi,
                COALESCE(pay.pay_card, 0) AS pay_card,
                COALESCE(pay.pay_mex_amt, 0) AS pay_mex_amt,
                COALESCE(pay.pay_mex_wt, 0) AS pay_mex_wt,
                COALESCE(pay.pay_scrap_amt, 0) AS pay_scrap_amt,
                COALESCE(pay.pay_scrap_wt, 0) AS pay_scrap_wt,
                COALESCE(pay.pay_fund, 0) AS pay_fund,
                COALESCE(pay.pay_adv, 0) AS pay_adv,
                COALESCE(pay.pay_types, '') AS pay_types_raw
            FROM tbl_sale_invoice_items sii
            INNER JOIN tbl_sale_invoices si ON si.id = sii.invoice_id
                AND LOWER(TRIM(IFNULL(si.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            LEFT JOIN tbl_products p ON p.id = sii.product_id
            LEFT JOIN tbl_categories cat ON cat.id = p.category_id
            LEFT JOIN tbl_product_characteristics pc ON pc.id = sii.product_characteristic_id AND pc.product_id = sii.product_id
            LEFT JOIN tbl_metal m ON m.id = pc.metal_id
            LEFT JOIN tbl_customers cust ON cust.id = si.customer_id AND cust.status = 1
            {$branch_join_si}
            LEFT JOIN (
                {$pay_si}
            ) pay ON pay.invoice_id = si.id
            WHERE DATE(si.invoice_date) BETWEEN '{$from_e}' AND '{$to_e}'
                AND IFNULL(sii.status, 1) = 1
                {$branch_where_si}
            LIMIT 2500
        ";

        /** POS Sales Invoice — same columns as standard for merge */
        $chk_psi = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_pos_sale_invoices'");
        $chk_psii = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_pos_sale_invoice_items'");
        $has_psi = $chk_psi && mysqli_num_rows($chk_psi) > 0;
        $has_psii = $chk_psii && mysqli_num_rows($chk_psii) > 0;
        if ($chk_psi) {
            mysqli_free_result($chk_psi);
        }
        if ($chk_psii) {
            mysqli_free_result($chk_psii);
        }
        if ($has_psi && $has_psii) {
            $branch_join_psi = '';
            $branch_sel_psi = '\'\' AS branch_display_name';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_pos_sale_invoices', 'branch_id')) {
                $branch_join_psi = 'LEFT JOIN tbl_branches br ON br.id = psi.branch_id';
                $branch_sel_psi = 'COALESCE(br.name, \'\') AS branch_display_name';
            }

            $huid_sel_pos = '\'-\' AS line_huid';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_pos_sale_invoice_items', 'huid_no')) {
                $huid_sel_pos = 'IFNULL(NULLIF(TRIM(psii.huid_no), \'\'), \'—\') AS line_huid';
            }

            $prev_bal_sel_pos = '0 AS prev_bal_used';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_pos_sale_invoices', 'previous_balance_used_amt')) {
                $prev_bal_sel_pos = 'COALESCE(psi.previous_balance_used_amt, 0) AS prev_bal_used';
            }

            $branch_where_psi = '';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_pos_sale_invoices', 'branch_id')
                && function_exists('auragold_effective_branch_id')) {
                $effPsi = (int) auragold_effective_branch_id();
                if ($effPsi > 0) {
                    $mainPsi = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
                    if ($mainPsi > 0 && $effPsi === $mainPsi) {
                        $branch_where_psi = " AND (psi.branch_id = {$effPsi} OR psi.branch_id IS NULL OR psi.branch_id = 0) ";
                    } else {
                        $branch_where_psi = " AND COALESCE(psi.branch_id, 0) = {$effPsi} ";
                    }
                }
            }

            $pay_psi = auragold_sale_analysis_pay_subquery_sql('tbl_pos_sale_invoice_payments');

            $branch_id_sel_psi = '0 AS branch_id_num';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_pos_sale_invoices', 'branch_id')) {
                $branch_id_sel_psi = 'COALESCE(psi.branch_id, 0) AS branch_id_num';
            }
            $carat_sel_psi = '\'\' AS line_carat';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_pos_sale_invoice_items', 'carat')) {
                $carat_sel_psi = 'IFNULL(psii.carat, \'\') AS line_carat';
            }

            $sources[] = "
                SELECT
                    psii.id AS line_id,
                    psi.invoice_no,
                    DATE_FORMAT(psi.invoice_date, '%Y-%m-%d') AS inv_sort_iso,
                    psi.customer_name AS party_name,
                    psi.sales_person,
                    DATE_FORMAT(psi.invoice_date, '%d-%m-%Y') AS inv_dt_dmY,
                    psi.against_of,
                    psi.currency,
                    psi.discount_amt,
                    psi.advance_payment,
                    psi.balance_amt,
                    psi.round_off,
                    psi.comment AS si_comment,
                    psi.additional_amt,
                    psi.grand_total AS si_grand_total,
                    psi.group_name,
                    'POS Sale Invoice' AS voucher_label,
                    COALESCE(psi.layaways_id, 0) AS layaways_id_num,
                    {$prev_bal_sel_pos},
                    psii.barcode,
                    psii.quantity AS pcs_qty,
                    psii.product_name,
                    psii.gross_weight,
                    psii.final_weight,
                    COALESCE(psii.metal_value, 0) AS metal_value_num,
                    psii.making_amount AS making_amt_num,
                    psii.amount AS amount_num,
                    psii.net_amount AS net_amount_num,
                    psii.tax_amount AS tax_amount_num,
                    psii.net_amt_with_tax AS line_grand_num,
                    COALESCE(cat.name, '') AS category_name,
                    COALESCE(p.article, '') AS product_article,
                    COALESCE(m.display_name, '') AS metal_display_name,
                    {$branch_id_sel_psi},
                    COALESCE(pc.metal_id, 0) AS metal_id_num,
                    COALESCE(psii.product_id, 0) AS product_id_num,
                    COALESCE(p.category_id, 0) AS category_id_num,
                    {$carat_sel_psi},
                    {$branch_sel_psi},
                    {$huid_sel_pos},
                    COALESCE(NULLIF(TRIM(cust.national_id), ''), NULLIF(TRIM(cust.identity_no), ''), '') AS cust_nid,
                    COALESCE(
                        NULLIF(TRIM(cust.mobile_no), ''),
                        NULLIF(TRIM(cust.phone_no), ''),
                        ''
                    ) AS cust_mobile,
                    COALESCE(pay.pay_cash, 0) AS pay_cash,
                    COALESCE(pay.pay_bank, 0) AS pay_bank,
                    COALESCE(pay.pay_cheque, 0) AS pay_cheque,
                    COALESCE(pay.pay_upi, 0) AS pay_upi,
                    COALESCE(pay.pay_card, 0) AS pay_card,
                    COALESCE(pay.pay_mex_amt, 0) AS pay_mex_amt,
                    COALESCE(pay.pay_mex_wt, 0) AS pay_mex_wt,
                    COALESCE(pay.pay_scrap_amt, 0) AS pay_scrap_amt,
                    COALESCE(pay.pay_scrap_wt, 0) AS pay_scrap_wt,
                    COALESCE(pay.pay_fund, 0) AS pay_fund,
                    COALESCE(pay.pay_adv, 0) AS pay_adv,
                    COALESCE(pay.pay_types, '') AS pay_types_raw
                FROM tbl_pos_sale_invoice_items psii
                INNER JOIN tbl_pos_sale_invoices psi ON psi.id = psii.invoice_id
                    AND LOWER(TRIM(IFNULL(psi.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
                LEFT JOIN tbl_products p ON p.id = psii.product_id
                LEFT JOIN tbl_categories cat ON cat.id = p.category_id
                LEFT JOIN tbl_product_characteristics pc ON pc.id = psii.product_characteristic_id AND pc.product_id = psii.product_id
                LEFT JOIN tbl_metal m ON m.id = pc.metal_id
                LEFT JOIN tbl_customers cust ON cust.id = psi.customer_id AND cust.status = 1
                {$branch_join_psi}
                LEFT JOIN (
                    {$pay_psi}
                ) pay ON pay.invoice_id = psi.id
                WHERE DATE(psi.invoice_date) BETWEEN '{$from_e}' AND '{$to_e}'
                    AND IFNULL(psii.status, 1) = 1
                    {$branch_where_psi}
                LIMIT 2500
            ";
        }

        /** Sale Quotation */
        $chk_sq = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_quotations'");
        $chk_sqi = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_quotation_items'");
        $has_sq = $chk_sq && mysqli_num_rows($chk_sq) > 0;
        $has_sqi = $chk_sqi && mysqli_num_rows($chk_sqi) > 0;
        if ($chk_sq) {
            mysqli_free_result($chk_sq);
        }
        if ($chk_sqi) {
            mysqli_free_result($chk_sqi);
        }
        if ($has_sq && $has_sqi) {
            $branch_join_sq = '';
            $branch_sel_sq = '\'\' AS branch_display_name';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_quotations', 'branch_id')) {
                $branch_join_sq = 'LEFT JOIN tbl_branches br ON br.id = sq.branch_id';
                $branch_sel_sq = 'COALESCE(br.name, \'\') AS branch_display_name';
            }

            $huid_sel_sq = '\'-\' AS line_huid';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_quotation_items', 'huid_no')) {
                $huid_sel_sq = 'IFNULL(NULLIF(TRIM(sqi.huid_no), \'\'), \'—\') AS line_huid';
            }

            $prev_bal_sel_sq = '0 AS prev_bal_used';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_quotations', 'previous_balance_used_amt')) {
                $prev_bal_sel_sq = 'COALESCE(sq.previous_balance_used_amt, 0) AS prev_bal_used';
            } elseif (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_quotations', 'adjusted_balance_used')) {
                $prev_bal_sel_sq = 'COALESCE(sq.adjusted_balance_used, 0) AS prev_bal_used';
            }

            $metal_val_sq = 'GREATEST(0, COALESCE(sqi.amount, 0) - COALESCE(sqi.making_amount, 0)) AS metal_value_num';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_quotation_items', 'metal_value')) {
                $metal_val_sq = 'COALESCE(sqi.metal_value, GREATEST(0, COALESCE(sqi.amount, 0) - COALESCE(sqi.making_amount, 0)), 0) AS metal_value_num';
            }

            $branch_where_sq = '';
            if (function_exists('auragold_sale_quotations_branch_where_sql')) {
                $branch_where_sq = auragold_sale_quotations_branch_where_sql($conn, 'sq');
            }

            $pay_sq_join = '';
            $pay_sq_sels = '
                0 AS pay_cash,
                0 AS pay_bank,
                0 AS pay_cheque,
                0 AS pay_upi,
                0 AS pay_card,
                0 AS pay_mex_amt,
                0 AS pay_mex_wt,
                0 AS pay_scrap_amt,
                0 AS pay_scrap_wt,
                0 AS pay_fund,
                0 AS pay_adv,
                \'\' AS pay_types_raw';
            $chk_sqp = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_quotation_payments'");
            $has_sqp = $chk_sqp && mysqli_num_rows($chk_sqp) > 0;
            if ($chk_sqp) {
                mysqli_free_result($chk_sqp);
            }
            if ($has_sqp) {
                $pay_sq = auragold_sale_analysis_pay_subquery_sql('tbl_sale_quotation_payments', 'quotation_id');
                $pay_sq_join = "LEFT JOIN (
                    {$pay_sq}
                ) pay ON pay.invoice_id = sq.id";
                $pay_sq_sels = '
                    COALESCE(pay.pay_cash, 0) AS pay_cash,
                    COALESCE(pay.pay_bank, 0) AS pay_bank,
                    COALESCE(pay.pay_cheque, 0) AS pay_cheque,
                    COALESCE(pay.pay_upi, 0) AS pay_upi,
                    COALESCE(pay.pay_card, 0) AS pay_card,
                    COALESCE(pay.pay_mex_amt, 0) AS pay_mex_amt,
                    COALESCE(pay.pay_mex_wt, 0) AS pay_mex_wt,
                    COALESCE(pay.pay_scrap_amt, 0) AS pay_scrap_amt,
                    COALESCE(pay.pay_scrap_wt, 0) AS pay_scrap_wt,
                    COALESCE(pay.pay_fund, 0) AS pay_fund,
                    COALESCE(pay.pay_adv, 0) AS pay_adv,
                    COALESCE(pay.pay_types, \'\') AS pay_types_raw';
            }

            $branch_id_sel_sq = '0 AS branch_id_num';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_quotations', 'branch_id')) {
                $branch_id_sel_sq = 'COALESCE(sq.branch_id, 0) AS branch_id_num';
            }
            $carat_sel_sq = '\'\' AS line_carat';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_quotation_items', 'carat')) {
                $carat_sel_sq = 'IFNULL(sqi.carat, \'\') AS line_carat';
            }

            $sources[] = "
                SELECT
                    sqi.id AS line_id,
                    sq.quotation_no AS invoice_no,
                    DATE_FORMAT(sq.quotation_date, '%Y-%m-%d') AS inv_sort_iso,
                    sq.customer_name AS party_name,
                    sq.sales_person,
                    DATE_FORMAT(sq.quotation_date, '%d-%m-%Y') AS inv_dt_dmY,
                    sq.against_of,
                    sq.currency,
                    sq.discount_amt,
                    sq.advance_payment,
                    sq.balance_amt,
                    sq.round_off,
                    sq.comment AS si_comment,
                    sq.additional_amt,
                    sq.grand_total AS si_grand_total,
                    sq.group_name,
                    'Sale Quotation' AS voucher_label,
                    COALESCE(sq.layaways_id, 0) AS layaways_id_num,
                    {$prev_bal_sel_sq},
                    sqi.barcode,
                    sqi.quantity AS pcs_qty,
                    sqi.product_name,
                    sqi.gross_weight,
                    sqi.final_weight,
                    {$metal_val_sq},
                    sqi.making_amount AS making_amt_num,
                    sqi.amount AS amount_num,
                    sqi.net_amount AS net_amount_num,
                    sqi.tax_amount AS tax_amount_num,
                    sqi.net_amt_with_tax AS line_grand_num,
                    COALESCE(cat.name, '') AS category_name,
                    COALESCE(p.article, '') AS product_article,
                    COALESCE(m.display_name, '') AS metal_display_name,
                    {$branch_id_sel_sq},
                    COALESCE(pc.metal_id, 0) AS metal_id_num,
                    COALESCE(sqi.product_id, 0) AS product_id_num,
                    COALESCE(p.category_id, 0) AS category_id_num,
                    {$carat_sel_sq},
                    {$branch_sel_sq},
                    {$huid_sel_sq},
                    COALESCE(NULLIF(TRIM(cust.national_id), ''), NULLIF(TRIM(cust.identity_no), ''), '') AS cust_nid,
                    COALESCE(
                        NULLIF(TRIM(cust.mobile_no), ''),
                        NULLIF(TRIM(cust.phone_no), ''),
                        ''
                    ) AS cust_mobile,
                    {$pay_sq_sels}
                FROM tbl_sale_quotation_items sqi
                INNER JOIN tbl_sale_quotations sq ON sq.id = sqi.quotation_id
                    AND LOWER(TRIM(IFNULL(sq.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
                LEFT JOIN tbl_products p ON p.id = sqi.product_id
                LEFT JOIN tbl_categories cat ON cat.id = p.category_id
                LEFT JOIN tbl_product_characteristics pc ON pc.id = sqi.product_characteristic_id AND pc.product_id = sqi.product_id
                LEFT JOIN tbl_metal m ON m.id = pc.metal_id
                LEFT JOIN tbl_customers cust ON cust.id = sq.customer_id AND cust.status = 1
                {$branch_join_sq}
                {$pay_sq_join}
                WHERE DATE(sq.quotation_date) BETWEEN '{$from_e}' AND '{$to_e}'
                    AND IFNULL(sqi.status, 1) = 1
                    {$branch_where_sq}
                LIMIT 2500
            ";
        }

        $rows = [];
        foreach ($sources as $sqlPart) {
            $chunk = function_exists('getList') ? getList($sqlPart) : [];
            if (is_array($chunk)) {
                foreach ($chunk as $r) {
                    $rows[] = $r;
                }
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $cmp = strcmp((string) ($b['inv_sort_iso'] ?? ''), (string) ($a['inv_sort_iso'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            $cmp = strcmp((string) ($b['invoice_no'] ?? ''), (string) ($a['invoice_no'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return ((int) ($a['line_id'] ?? 0)) <=> ((int) ($b['line_id'] ?? 0));
        });

        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $r) {
            $metal_disp = trim((string) ($r['metal_display_name'] ?? ''));
            $cat_nm = trim((string) ($r['category_name'] ?? ''));
            $grp = trim((string) ($r['group_name'] ?? ''));
            $voucher_label = trim((string) ($r['voucher_label'] ?? 'Sale Invoice'));
            $ledger_parts = [];
            if ($voucher_label !== '') {
                $ledger_parts[] = $voucher_label;
            }
            if ($grp !== '') {
                $ledger_parts[] = $grp;
            }
            if ($metal_disp !== '') {
                $ledger_parts[] = $metal_disp;
            } elseif ($cat_nm !== '') {
                $ledger_parts[] = $cat_nm;
            }
            $ledger_name = $ledger_parts !== [] ? implode(' — ', $ledger_parts) : ($cat_nm !== '' ? $cat_nm : 'Sales');

            $line_net_tax = (float) ($r['line_grand_num'] ?? 0);
            $metal_v = (float) ($r['metal_value_num'] ?? 0);
            $mk = (float) ($r['making_amt_num'] ?? 0);
            $profit_est = $line_net_tax - $metal_v - $mk;

            $lay_id = (int) ($r['layaways_id_num'] ?? 0);
            $lay_status = $lay_id > 0 ? 'Active' : '—';

            $pay_raw = (string) ($r['pay_types_raw'] ?? '');
            $txn_label = auragold_sale_analysis_format_pay_types($pay_raw);

            $fmt2 = static function ($v): string {
                return number_format((float) $v, 2, '.', '');
            };
            $fmt3 = static function ($v): string {
                return number_format((float) $v, 3, '.', '');
            };

            $voucher_key = 'si';
            if (stripos($voucher_label, 'quotation') !== false) {
                $voucher_key = 'sq';
            } elseif (stripos($voucher_label, 'pos') !== false) {
                $voucher_key = 'pos';
            }

            $out[] = [
                'ledger_name'          => $ledger_name,
                'party'                => (string) ($r['party_name'] ?? ''),
                'sales_person'         => trim((string) ($r['sales_person'] ?? '')),
                'invoice_no'           => (string) ($r['invoice_no'] ?? ''),
                'branch'               => (string) ($r['branch_display_name'] ?? ''),
                'date'                 => (string) ($r['inv_dt_dmY'] ?? ''),
                'barcode'              => trim((string) ($r['barcode'] ?? '')),
                'pcs'                  => $fmt3((float) ($r['pcs_qty'] ?? 0)),
                'category'             => $cat_nm,
                'product'              => (string) ($r['product_name'] ?? ''),
                'gross_wt'             => $fmt3((float) ($r['gross_weight'] ?? 0)),
                'final_wt'             => $fmt3((float) ($r['final_weight'] ?? 0)),
                'metal_amt'            => $fmt2($metal_v),
                // Filter meta (not shown as table columns)
                'filter_voucher'       => $voucher_key,
                'filter_metal_id'      => (int) ($r['metal_id_num'] ?? 0),
                'filter_metal_name'    => $metal_disp,
                'filter_product_id'    => (int) ($r['product_id_num'] ?? 0),
                'filter_category_id'   => (int) ($r['category_id_num'] ?? 0),
                'filter_branch_id'     => (int) ($r['branch_id_num'] ?? 0),
                'filter_karat'         => trim((string) ($r['line_carat'] ?? '')),
                'filter_account_no'    => trim((string) ($r['cust_nid'] ?? '')),
                'making_amt'           => $fmt2($mk),
                'amount'               => $fmt2((float) ($r['amount_num'] ?? 0)),
                'sales_amt'            => $fmt2((float) ($r['net_amount_num'] ?? 0)),
                'tax_amount'           => $fmt2((float) ($r['tax_amount_num'] ?? 0)),
                'making_cost'          => '',
                'cost_price'           => $metal_v > 0 ? $fmt2($metal_v) : '',
                'profit'               => $fmt2($profit_est),
                'grand_total'          => $fmt2($line_net_tax),
                'discount'             => $fmt2((float) ($r['discount_amt'] ?? 0)),
                'cash'                 => $fmt2((float) ($r['pay_cash'] ?? 0)),
                'bank'                 => $fmt2((float) ($r['pay_bank'] ?? 0)),
                'transaction_name'     => $txn_label,
                'cheque'               => $fmt2((float) ($r['pay_cheque'] ?? 0)),
                'upi'                  => $fmt2((float) ($r['pay_upi'] ?? 0)),
                'card'                 => $fmt2((float) ($r['pay_card'] ?? 0)),
                'metal_exch_amt'       => $fmt2((float) ($r['pay_mex_amt'] ?? 0)),
                'metal_exch_wt'        => $fmt3((float) ($r['pay_mex_wt'] ?? 0)),
                'old_jew_amt'          => $fmt2((float) ($r['pay_scrap_amt'] ?? 0)),
                'old_jew_wt'           => $fmt3((float) ($r['pay_scrap_wt'] ?? 0)),
                'huid_no'              => (string) ($r['line_huid'] ?? ''),
                'balance_amt'          => $fmt2((float) ($r['balance_amt'] ?? 0)),
                'comment'              => trim((string) ($r['si_comment'] ?? '')),
                'currency'             => trim((string) ($r['currency'] ?? '')),
                'layaways_status'      => $lay_status,
                'advance_payment'      => $fmt2((float) ($r['advance_payment'] ?? 0)),
                'round_off'            => $fmt2((float) ($r['round_off'] ?? 0)),
                'from_prev_balance'    => $fmt2((float) ($r['prev_bal_used'] ?? 0)),
                'return_amount'        => '',
                'additional_amount'    => $fmt2((float) ($r['additional_amt'] ?? 0)),
                'customer_advance'     => $fmt2((float) ($r['pay_adv'] ?? 0)),
                'fund_transfer'        => $fmt2((float) ($r['pay_fund'] ?? 0)),
                'sale_order_advance'   => '',
                'article'              => trim((string) ($r['product_article'] ?? '')),
                'national_id'          => trim((string) ($r['cust_nid'] ?? '')),
                'mobile_no'            => trim((string) ($r['cust_mobile'] ?? '')),
            ];
        }

        return $out;
    }
}

if (!function_exists('auragold_sale_analysis_int_list_param')) {
    /** @return list<int> */
    function auragold_sale_analysis_int_list_param(string $key): array {
        $raw = $_GET[$key] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $out = [];
        foreach ($raw as $v) {
            $n = (int) $v;
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }
}

if (!function_exists('auragold_sale_analysis_str_list_param')) {
    /** @return list<string> */
    function auragold_sale_analysis_str_list_param(string $key): array {
        $raw = $_GET[$key] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }
        $out = [];
        foreach ($raw as $v) {
            $s = trim((string) $v);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }
}

if (!function_exists('auragold_sale_analysis_parse_filters')) {
    /**
     * @return array<string, mixed>
     */
    function auragold_sale_analysis_parse_filters(): array {
        $fromYmd = '';
        $toYmd = '';
        $df = trim((string) ($_GET['date_from'] ?? ''));
        $dt = trim((string) ($_GET['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $df) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
            $fromYmd = $df;
            $toYmd = $dt;
        } else {
            $label = '';
            if (!empty($_GET['sa_from']) && !empty($_GET['sa_to'])) {
                $label = trim((string) $_GET['sa_from']) . ' - ' . trim((string) $_GET['sa_to']);
            }
            $parsed = auragold_sale_analysis_parse_range($label);
            $fromYmd = $parsed['from_ymd'];
            $toYmd = $parsed['to_ymd'];
        }

        $above = trim((string) ($_GET['adv_above_amount'] ?? ''));
        $gross = trim((string) ($_GET['adv_gross_wt'] ?? ''));
        $voucher = strtolower(trim((string) ($_GET['adv_voucher_type'] ?? '')));
        if (!in_array($voucher, ['si', 'sq', 'pos'], true)) {
            $voucher = '';
        }
        $lay = strtolower(trim((string) ($_GET['adv_layaways_status'] ?? '')));
        if (!in_array($lay, ['active', 'none'], true)) {
            $lay = '';
        }

        return [
            'date_from' => $fromYmd,
            'date_to' => $toYmd,
            'branch_ids' => auragold_sale_analysis_int_list_param('adv_branch'),
            'ledger_name' => trim((string) ($_GET['adv_ledger_name'] ?? '')),
            'account_no' => trim((string) ($_GET['adv_account_no'] ?? '')),
            'sales_person' => trim((string) ($_GET['adv_sales_person'] ?? '')),
            'voucher_type' => $voucher,
            'metal_ids' => auragold_sale_analysis_int_list_param('adv_metal'),
            'product_ids' => auragold_sale_analysis_int_list_param('adv_product'),
            'category_ids' => auragold_sale_analysis_int_list_param('adv_category'),
            'karats' => auragold_sale_analysis_str_list_param('adv_karat'),
            'articles' => auragold_sale_analysis_str_list_param('adv_article'),
            'currency' => trim((string) ($_GET['adv_currency'] ?? '')),
            'above_amount' => ($above !== '' && is_numeric($above)) ? (float) $above : null,
            'barcode' => trim((string) ($_GET['adv_barcode'] ?? '')),
            'invoice_no' => trim((string) ($_GET['adv_invoice_no'] ?? '')),
            'gross_wt' => ($gross !== '' && is_numeric($gross)) ? (float) $gross : null,
            'ledger_type' => trim((string) ($_GET['adv_ledger_type'] ?? '')),
            'comment' => trim((string) ($_GET['adv_comment'] ?? '')),
            'layaways_status' => $lay,
        ];
    }
}

if (!function_exists('auragold_sale_analysis_filter_count')) {
    function auragold_sale_analysis_filter_count(array $filters): int {
        $n = 0;
        foreach (['branch_ids', 'metal_ids', 'product_ids', 'category_ids', 'karats', 'articles'] as $k) {
            if (!empty($filters[$k])) {
                $n++;
            }
        }
        foreach (['ledger_name', 'account_no', 'sales_person', 'voucher_type', 'currency', 'barcode', 'invoice_no', 'ledger_type', 'comment', 'layaways_status'] as $k) {
            if (($filters[$k] ?? '') !== '') {
                $n++;
            }
        }
        if ($filters['above_amount'] !== null) {
            $n++;
        }
        if ($filters['gross_wt'] !== null) {
            $n++;
        }

        return $n;
    }
}

if (!function_exists('auragold_sale_analysis_filter_options')) {
    /** @return array<string, mixed> */
    function auragold_sale_analysis_filter_options($conn): array {
        $empty = [
            'branch_groups' => [],
            'metals' => [],
            'products' => [],
            'categories' => [],
            'karats' => [],
            'articles' => [],
            'currencies' => [],
            'persons' => [],
            'ledgers' => [],
        ];
        if (!$conn instanceof mysqli || !function_exists('getList')) {
            return $empty;
        }
        $safeList = static function (string $sql) {
            try {
                $rows = getList($sql);

                return is_array($rows) ? $rows : [];
            } catch (Throwable $e) {
                return [];
            }
        };
        $branchGroups = [];
        if (function_exists('auragold_um_branch_picker_groups')) {
            global $conn_master;
            $g = auragold_um_branch_picker_groups($conn, $conn_master ?? null);
            $branchGroups = is_array($g) ? $g : [];
        } elseif (function_exists('auragold_purchase_financial_branch_groups')) {
            $branchGroups = auragold_purchase_financial_branch_groups($conn);
        }

        $currencies = [];
        if (function_exists('auragold_load_master_currencies')) {
            foreach (auragold_load_master_currencies($conn) as $c) {
                $n = trim((string) ($c['name'] ?? ''));
                if ($n !== '') {
                    $currencies[] = ['currency' => $n];
                }
            }
        }
        if ($currencies === []) {
            $currencies = $safeList("
                SELECT DISTINCT TRIM(currency) AS currency FROM (
                    SELECT currency FROM tbl_sale_invoices WHERE currency IS NOT NULL AND TRIM(currency) != ''
                    UNION
                    SELECT currency FROM tbl_sale_quotations WHERE currency IS NOT NULL AND TRIM(currency) != ''
                ) u ORDER BY currency ASC
            ");
        }

        return [
            'branch_groups' => $branchGroups,
            'metals' => $safeList("SELECT id, display_name AS name FROM tbl_metal WHERE IFNULL(status, 1) = 1 ORDER BY display_name ASC"),
            'products' => $safeList("SELECT id, name FROM tbl_products WHERE IFNULL(status, 1) = 1 ORDER BY name ASC LIMIT 800"),
            'categories' => $safeList("SELECT id, name FROM tbl_categories WHERE IFNULL(status, 1) = 1 ORDER BY name ASC"),
            'karats' => $safeList("
                SELECT DISTINCT TRIM(name) AS name FROM tbl_carat
                WHERE IFNULL(status, 1) = 1 AND name IS NOT NULL AND TRIM(name) != ''
                ORDER BY name ASC LIMIT 200
            "),
            'articles' => $safeList("
                SELECT DISTINCT TRIM(p.article) AS article
                FROM tbl_products p
                WHERE p.article IS NOT NULL AND TRIM(p.article) != '' AND IFNULL(p.status, 1) = 1
                ORDER BY article ASC LIMIT 400
            "),
            'currencies' => $currencies,
            'persons' => $safeList("
                SELECT DISTINCT TRIM(sales_person) AS sales_person FROM (
                    SELECT sales_person FROM tbl_sale_invoices WHERE sales_person IS NOT NULL AND TRIM(sales_person) != ''
                    UNION
                    SELECT sales_person FROM tbl_sale_quotations WHERE sales_person IS NOT NULL AND TRIM(sales_person) != ''
                ) u ORDER BY sales_person ASC
            "),
            'ledgers' => $safeList("
                SELECT DISTINCT TRIM(customer_name) AS name FROM (
                    SELECT customer_name FROM tbl_sale_invoices WHERE customer_name IS NOT NULL AND TRIM(customer_name) != ''
                    UNION
                    SELECT customer_name FROM tbl_sale_quotations WHERE customer_name IS NOT NULL AND TRIM(customer_name) != ''
                ) u ORDER BY name ASC LIMIT 500
            "),
        ];
    }
}

if (!function_exists('auragold_sale_analysis_apply_filters')) {
    /**
     * @param list<array<string, string>> $rows
     * @param array<string, mixed> $filters
     * @return list<array<string, string>>
     */
    function auragold_sale_analysis_apply_filters(array $rows, array $filters): array {
        $out = [];
        foreach ($rows as $row) {
            if (!empty($filters['branch_ids'])) {
                $bid = (int) ($row['filter_branch_id'] ?? 0);
                $bname = trim((string) ($row['branch'] ?? ''));
                $ok = in_array($bid, $filters['branch_ids'], true);
                if (!$ok && $bname !== '') {
                    // Fallback: match when branch_id missing but name matches selected IDs' names is hard; skip name-only
                }
                if (!$ok) {
                    continue;
                }
            }
            if (($filters['ledger_name'] ?? '') !== '') {
                $party = (string) ($row['party'] ?? '');
                if (strcasecmp($party, (string) $filters['ledger_name']) !== 0
                    && stripos((string) ($row['ledger_name'] ?? ''), (string) $filters['ledger_name']) === false) {
                    continue;
                }
            }
            if (($filters['account_no'] ?? '') !== '') {
                $q = (string) $filters['account_no'];
                $hay = ((string) ($row['filter_account_no'] ?? '')) . ' '
                    . ((string) ($row['national_id'] ?? '')) . ' '
                    . ((string) ($row['mobile_no'] ?? '')) . ' '
                    . ((string) ($row['invoice_no'] ?? ''));
                if (stripos($hay, $q) === false) {
                    continue;
                }
            }
            if (($filters['sales_person'] ?? '') !== '') {
                if (strcasecmp(trim((string) ($row['sales_person'] ?? '')), (string) $filters['sales_person']) !== 0) {
                    continue;
                }
            }
            if (($filters['voucher_type'] ?? '') !== '') {
                if ((string) ($row['filter_voucher'] ?? '') !== (string) $filters['voucher_type']) {
                    continue;
                }
            }
            if (!empty($filters['metal_ids'])) {
                if (!in_array((int) ($row['filter_metal_id'] ?? 0), $filters['metal_ids'], true)) {
                    continue;
                }
            }
            if (!empty($filters['product_ids'])) {
                if (!in_array((int) ($row['filter_product_id'] ?? 0), $filters['product_ids'], true)) {
                    continue;
                }
            }
            if (!empty($filters['category_ids'])) {
                if (!in_array((int) ($row['filter_category_id'] ?? 0), $filters['category_ids'], true)) {
                    continue;
                }
            }
            if (!empty($filters['karats'])) {
                $k = trim((string) ($row['filter_karat'] ?? ''));
                $hit = false;
                foreach ($filters['karats'] as $want) {
                    if ($k !== '' && strcasecmp($k, (string) $want) === 0) {
                        $hit = true;
                        break;
                    }
                    if ($k !== '' && stripos($k, (string) $want) !== false) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
            }
            if (!empty($filters['articles'])) {
                $art = trim((string) ($row['article'] ?? ''));
                $hit = false;
                foreach ($filters['articles'] as $want) {
                    if ($art !== '' && strcasecmp($art, (string) $want) === 0) {
                        $hit = true;
                        break;
                    }
                }
                if (!$hit) {
                    continue;
                }
            }
            if (($filters['currency'] ?? '') !== '') {
                if (strcasecmp(trim((string) ($row['currency'] ?? '')), (string) $filters['currency']) !== 0) {
                    continue;
                }
            }
            if ($filters['above_amount'] !== null) {
                $amt = (float) ($row['amount'] ?? 0);
                $gt = (float) ($row['grand_total'] ?? 0);
                if (max($amt, $gt) < (float) $filters['above_amount']) {
                    continue;
                }
            }
            if (($filters['barcode'] ?? '') !== '') {
                if (stripos((string) ($row['barcode'] ?? ''), (string) $filters['barcode']) === false) {
                    continue;
                }
            }
            if (($filters['invoice_no'] ?? '') !== '') {
                if (stripos((string) ($row['invoice_no'] ?? ''), (string) $filters['invoice_no']) === false) {
                    continue;
                }
            }
            if ($filters['gross_wt'] !== null) {
                if ((float) ($row['gross_wt'] ?? 0) < (float) $filters['gross_wt']) {
                    continue;
                }
            }
            if (($filters['ledger_type'] ?? '') !== '') {
                $lt = strtolower((string) $filters['ledger_type']);
                $ledger = strtolower((string) ($row['ledger_name'] ?? ''));
                if ($lt === 'customer' && stripos($ledger, 'sale') === false && trim((string) ($row['party'] ?? '')) === '') {
                    continue;
                }
                if ($lt === 'metal' && trim((string) ($row['filter_metal_name'] ?? '')) === '') {
                    continue;
                }
            }
            if (($filters['comment'] ?? '') !== '') {
                if (stripos((string) ($row['comment'] ?? ''), (string) $filters['comment']) === false) {
                    continue;
                }
            }
            if (($filters['layaways_status'] ?? '') !== '') {
                $ls = strtolower(trim((string) ($row['layaways_status'] ?? '')));
                if ($filters['layaways_status'] === 'active' && $ls !== 'active') {
                    continue;
                }
                if ($filters['layaways_status'] === 'none' && $ls === 'active') {
                    continue;
                }
            }
            $out[] = $row;
        }

        return $out;
    }
}
