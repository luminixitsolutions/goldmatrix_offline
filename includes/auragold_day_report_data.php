<?php

/**
 * Shared day report data for JSON API and Excel export.
 *
 * @return array{sections: array, transactions: array,summary: array<string,float>}
 */
function auragold_day_report_collect($conn, string $report_date): array
{
    if (!$conn instanceof mysqli) {
        throw new InvalidArgumentException('Invalid database connection');
    }
    $report_date = trim($report_date);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date)) {
        throw new InvalidArgumentException('Invalid date');
    }
    $report_date = esc($report_date);

    $previous_date = date('Y-m-d', strtotime($report_date . ' -1 day'));

    $saved_report = null;
    $check_table  = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_day_reports'");
    if ($check_table && mysqli_num_rows($check_table) > 0) {
        $saved_report = getRecord("SELECT closing_cash FROM tbl_day_reports WHERE report_date = '$previous_date'");
    }

    if ($saved_report && isset($saved_report['closing_cash'])) {
        $opening_amount = (float) $saved_report['closing_cash'];
    } else {
        $opening_query = "
            SELECT
                COALESCE(SUM(
                    CASE
                        WHEN transaction_type = 'sale' THEN amount
                        WHEN transaction_type = 'purchase' THEN -amount
                        ELSE 0
                    END
                ), 0) AS opening_cash
            FROM (
                SELECT sip.amount, 'sale' AS transaction_type
                FROM tbl_sale_invoice_payments sip
                INNER JOIN tbl_sale_invoices si ON sip.invoice_id = si.id
                WHERE DATE(si.invoice_date) < '$report_date' AND sip.status = 1 AND sip.payment_type = 'cash'
                  AND LOWER(TRIM(IFNULL(si.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
                UNION ALL
                SELECT pip.amount, 'purchase' AS transaction_type
                FROM tbl_purchase_invoice_payments pip
                INNER JOIN tbl_purchase_invoices pi ON pip.invoice_id = pi.id
                WHERE DATE(pi.invoice_date) < '$report_date' AND pip.status = 1 AND pip.payment_type = 'cash'
                  AND LOWER(TRIM(IFNULL(pi.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
                UNION ALL
                SELECT sop.amount, 'sale' AS transaction_type
                FROM tbl_sale_order_payments sop
                INNER JOIN tbl_sale_orders so ON sop.order_id = so.id
                WHERE DATE(so.order_date) < '$report_date' AND sop.status = 1 AND sop.payment_type = 'cash'
                  AND LOWER(TRIM(IFNULL(so.status, ''))) NOT IN ('draft', 'cancelled', 'void', 'deleted')
            ) AS all_cash_transactions
        ";
        $opening_result = getRecord($opening_query);
        $opening_amount = (float) ($opening_result['opening_cash'] ?? 0);
    }

    $sale_query = "
        SELECT
            si.id,
            si.invoice_no,
            si.customer_name,
            si.invoice_date,
            si.grand_total,
            COALESCE(SUM(CASE WHEN sip.payment_type = 'cash' THEN sip.amount ELSE 0 END), 0) AS cash_amount,
            COALESCE(SUM(CASE WHEN sip.payment_type IN ('bank', 'cheque', 'upi', 'card') THEN sip.amount ELSE 0 END), 0) AS online_cheque_amount,
            COALESCE(SUM(sii.final_weight), 0) AS total_weight
        FROM tbl_sale_invoices si
        LEFT JOIN tbl_sale_invoice_payments sip ON si.id = sip.invoice_id AND sip.status = 1
        LEFT JOIN tbl_sale_invoice_items sii ON si.id = sii.invoice_id AND sii.status = 1
        WHERE DATE(si.invoice_date) = '$report_date' AND LOWER(TRIM(IFNULL(si.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
        GROUP BY si.id, si.invoice_no, si.customer_name, si.invoice_date, si.grand_total
        ORDER BY si.invoice_date ASC, si.id ASC
    ";
    $sale_invoices = getList($sale_query);
    if (!is_array($sale_invoices)) {
        $sale_invoices = [];
    }

    $sale_order_query = "
        SELECT
            so.id,
            so.order_no,
            so.customer_name,
            so.order_date,
            so.grand_total,
            COALESCE(SUM(CASE WHEN sop.payment_type = 'cash' THEN sop.amount ELSE 0 END), 0) AS cash_amount,
            COALESCE(SUM(CASE WHEN sop.payment_type IN ('bank', 'cheque', 'upi', 'card') THEN sop.amount ELSE 0 END), 0) AS online_cheque_amount,
            COALESCE(SUM(soi.final_weight), 0) AS total_weight
        FROM tbl_sale_orders so
        LEFT JOIN tbl_sale_order_payments sop ON so.id = sop.order_id AND sop.status = 1
        LEFT JOIN tbl_sale_order_items soi ON so.id = soi.order_id
        WHERE DATE(so.order_date) = '$report_date' AND LOWER(TRIM(IFNULL(so.status, ''))) NOT IN ('draft', 'cancelled', 'void', 'deleted')
        GROUP BY so.id, so.order_no, so.customer_name, so.order_date, so.grand_total
        ORDER BY so.order_date ASC, so.id ASC
    ";
    $sale_orders = getList($sale_order_query);
    if (!is_array($sale_orders)) {
        $sale_orders = [];
    }

    $purchase_query = "
        SELECT
            pi.id,
            pi.invoice_no,
            pi.supplier_name,
            pi.invoice_date,
            pi.grand_total,
            COALESCE(SUM(CASE WHEN pip.payment_type = 'cash' THEN pip.amount ELSE 0 END), 0) AS cash_amount,
            COALESCE(SUM(CASE WHEN pip.payment_type IN ('bank', 'cheque', 'upi', 'card') THEN pip.amount ELSE 0 END), 0) AS online_cheque_amount,
            COALESCE(SUM(pii.final_weight), 0) AS total_weight
        FROM tbl_purchase_invoices pi
        LEFT JOIN tbl_purchase_invoice_payments pip ON pi.id = pip.invoice_id AND pip.status = 1
        LEFT JOIN tbl_purchase_invoice_items pii ON pi.id = pii.invoice_id AND pii.status = 1
        WHERE DATE(pi.invoice_date) = '$report_date' AND LOWER(TRIM(IFNULL(pi.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
        GROUP BY pi.id, pi.invoice_no, pi.supplier_name, pi.invoice_date, pi.grand_total
        ORDER BY pi.invoice_date ASC, pi.id ASC
    ";
    $purchase_invoices = getList($purchase_query);
    if (!is_array($purchase_invoices)) {
        $purchase_invoices = [];
    }

    $sale_invoice_rows = [];
    foreach ($sale_invoices as $invoice) {
        $sale_invoice_rows[] = [
            'description' => day_report_prefix_doc_no('SI', $invoice['invoice_no'] ?? ''),
            'debit'       => 0,
            'credit'      => (float) $invoice['grand_total'],
            'inward_wt'   => 0,
            'outward_wt'  => (float) $invoice['total_weight'],
        ];
    }

    $pos_invoices = [];
    if (day_report_tbl_exists($conn, 'tbl_pos_sale_invoices')) {
        $pos_q = "
            SELECT
                psi.id,
                psi.invoice_no,
                psi.invoice_date,
                psi.grand_total,
                COALESCE(SUM(CASE WHEN psp.payment_type = 'cash' THEN psp.amount ELSE 0 END), 0) AS cash_amount,
                COALESCE(SUM(CASE WHEN psp.payment_type IN ('bank', 'cheque', 'upi', 'card') THEN psp.amount ELSE 0 END), 0) AS online_cheque_amount,
                COALESCE(SUM(psii.final_weight), 0) AS total_weight
            FROM tbl_pos_sale_invoices psi
            LEFT JOIN tbl_pos_sale_invoice_payments psp ON psi.id = psp.invoice_id AND psp.status = 1
            LEFT JOIN tbl_pos_sale_invoice_items psii ON psi.id = psii.invoice_id
            WHERE DATE(psi.invoice_date) = '$report_date' AND LOWER(TRIM(IFNULL(psi.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY psi.id, psi.invoice_no, psi.invoice_date, psi.grand_total
            ORDER BY psi.id ASC
        ";
        $pos_invoices = getList($pos_q);
        if (!is_array($pos_invoices)) {
            $pos_invoices = [];
        }
    }
    $pos_rows = [];
    foreach ($pos_invoices as $inv) {
        $pos_rows[] = [
            'description' => day_report_prefix_doc_no('PSI', $inv['invoice_no'] ?? ''),
            'debit'       => 0,
            'credit'      => (float) $inv['grand_total'],
            'inward_wt'   => 0,
            'outward_wt'  => (float) $inv['total_weight'],
        ];
    }

    $sale_quotations = [];
    if (day_report_tbl_exists($conn, 'tbl_sale_quotations')) {
        $sale_quotations = getList("
            SELECT sq.id, sq.quotation_no, sq.grand_total, sq.quotation_date,
                COALESCE(SUM(sqi.final_weight), 0) AS total_weight
            FROM tbl_sale_quotations sq
            LEFT JOIN tbl_sale_quotation_items sqi ON sq.id = sqi.quotation_id
            WHERE DATE(sq.quotation_date) = '$report_date' AND LOWER(TRIM(IFNULL(sq.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY sq.id, sq.quotation_no, sq.grand_total, sq.quotation_date
            ORDER BY sq.id ASC
        ");
        if (!is_array($sale_quotations)) {
            $sale_quotations = [];
        }
    }
    $sale_quotation_rows = [];
    foreach ($sale_quotations as $q) {
        $sale_quotation_rows[] = [
            'description' => day_report_prefix_doc_no('SQ', $q['quotation_no'] ?? ''),
            'debit'       => 0,
            'credit'      => (float) $q['grand_total'],
            'inward_wt'   => 0,
            'outward_wt'  => (float) $q['total_weight'],
        ];
    }

    $sale_returns = [];
    if (day_report_tbl_exists($conn, 'tbl_sale_returns')) {
        $sale_returns = getList("
            SELECT sr.id, sr.return_no, sr.grand_total, sr.return_date,
                COALESCE(SUM(sri.final_weight), 0) AS total_weight
            FROM tbl_sale_returns sr
            LEFT JOIN tbl_sale_return_items sri ON sr.id = sri.return_id
            WHERE DATE(sr.return_date) = '$report_date' AND LOWER(TRIM(IFNULL(sr.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY sr.id, sr.return_no, sr.grand_total, sr.return_date
            ORDER BY sr.id ASC
        ");
        if (!is_array($sale_returns)) {
            $sale_returns = [];
        }
    }
    $sale_return_rows = [];
    foreach ($sale_returns as $r) {
        $sale_return_rows[] = [
            'description' => day_report_prefix_doc_no('SR', $r['return_no'] ?? ''),
            'debit'       => (float) $r['grand_total'],
            'credit'      => 0,
            'inward_wt'   => (float) $r['total_weight'],
            'outward_wt'  => 0,
        ];
    }

    $purchase_quotations = [];
    if (day_report_tbl_exists($conn, 'tbl_purchase_quotations')) {
        $purchase_quotations = getList("
            SELECT pq.id, pq.quotation_no, pq.grand_total, pq.quotation_date,
                COALESCE(SUM(pqi.final_weight), 0) AS total_weight
            FROM tbl_purchase_quotations pq
            LEFT JOIN tbl_purchase_quotation_items pqi ON pq.id = pqi.quotation_id
            WHERE DATE(pq.quotation_date) = '$report_date' AND LOWER(TRIM(IFNULL(pq.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY pq.id, pq.quotation_no, pq.grand_total, pq.quotation_date
            ORDER BY pq.id ASC
        ");
        if (!is_array($purchase_quotations)) {
            $purchase_quotations = [];
        }
    }
    $purchase_quotation_rows = [];
    foreach ($purchase_quotations as $q) {
        $purchase_quotation_rows[] = [
            'description' => day_report_prefix_doc_no('PQ', $q['quotation_no'] ?? ''),
            'debit'       => (float) $q['grand_total'],
            'credit'      => 0,
            'inward_wt'   => (float) $q['total_weight'],
            'outward_wt'  => 0,
        ];
    }

    $purchase_returns = [];
    if (day_report_tbl_exists($conn, 'tbl_purchase_returns')) {
        $purchase_returns = getList("
            SELECT pr.id, pr.return_no, pr.grand_total, pr.return_date,
                COALESCE(SUM(pri.final_weight), 0) AS total_weight
            FROM tbl_purchase_returns pr
            LEFT JOIN tbl_purchase_return_items pri ON pr.id = pri.return_id
            WHERE DATE(pr.return_date) = '$report_date' AND LOWER(TRIM(IFNULL(pr.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY pr.id, pr.return_no, pr.grand_total, pr.return_date
            ORDER BY pr.id ASC
        ");
        if (!is_array($purchase_returns)) {
            $purchase_returns = [];
        }
    }
    $purchase_return_rows = [];
    foreach ($purchase_returns as $r) {
        $purchase_return_rows[] = [
            'description' => day_report_prefix_doc_no('PR', $r['return_no'] ?? ''),
            'debit'       => 0,
            'credit'      => (float) $r['grand_total'],
            'inward_wt'   => 0,
            'outward_wt'  => (float) $r['total_weight'],
        ];
    }

    $scrap_invoices = [];
    if (day_report_tbl_exists($conn, 'tbl_old_jewelry_scrap_invoices')) {
        $scrap_invoices = getList("
            SELECT oj.id, oj.invoice_no, oj.grand_total, oj.invoice_date,
                COALESCE(SUM(COALESCE(oji.final_wt, oji.gross_wt, 0)), 0) AS total_weight
            FROM tbl_old_jewelry_scrap_invoices oj
            LEFT JOIN tbl_old_jewelry_scrap_invoice_items oji ON oj.id = oji.invoice_id
            WHERE DATE(oj.invoice_date) = '$report_date' AND LOWER(TRIM(IFNULL(oj.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY oj.id, oj.invoice_no, oj.grand_total, oj.invoice_date
            ORDER BY oj.id ASC
        ");
        if (!is_array($scrap_invoices)) {
            $scrap_invoices = [];
        }
    }
    $scrap_rows = [];
    foreach ($scrap_invoices as $inv) {
        $scrap_rows[] = [
            'description' => day_report_prefix_doc_no('OJ', $inv['invoice_no'] ?? ''),
            'debit'       => (float) $inv['grand_total'],
            'credit'      => 0,
            'inward_wt'   => (float) $inv['total_weight'],
            'outward_wt'  => 0,
        ];
    }

    $payment_vouchers = [];
    if (day_report_tbl_exists($conn, 'tbl_payment_vouchers')) {
        $payment_vouchers = getList("
            SELECT pv.id, pv.voucher_no, pv.total_amount, pv.voucher_date,
                COALESCE(SUM(pvi.weight), 0) AS total_weight
            FROM tbl_payment_vouchers pv
            LEFT JOIN tbl_payment_voucher_items pvi ON pv.id = pvi.voucher_id
            WHERE DATE(pv.voucher_date) = '$report_date' AND LOWER(TRIM(IFNULL(pv.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY pv.id, pv.voucher_no, pv.total_amount, pv.voucher_date
            ORDER BY pv.id ASC
        ");
        if (!is_array($payment_vouchers)) {
            $payment_vouchers = [];
        }
    }
    $payment_voucher_rows = [];
    foreach ($payment_vouchers as $v) {
        $amt = (float) $v['total_amount'];
        $payment_voucher_rows[] = [
            'description' => day_report_prefix_doc_no('PV', $v['voucher_no'] ?? ''),
            'debit'       => $amt,
            'credit'      => 0,
            'inward_wt'   => 0,
            'outward_wt'  => (float) ($v['total_weight'] ?? 0),
        ];
    }

    $receipt_vouchers = [];
    if (day_report_tbl_exists($conn, 'tbl_receipt_vouchers')) {
        $receipt_vouchers = getList("
            SELECT rv.id, rv.voucher_no, rv.total_amount, rv.voucher_date,
                COALESCE(SUM(COALESCE(rvi.weight, 0)), 0) AS total_weight
            FROM tbl_receipt_vouchers rv
            LEFT JOIN tbl_receipt_voucher_items rvi ON rv.id = rvi.voucher_id
            WHERE DATE(rv.voucher_date) = '$report_date' AND LOWER(TRIM(IFNULL(rv.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
              AND COALESCE(rv.voucher_type,'') <> 'Sale Invoice Payment'
            GROUP BY rv.id, rv.voucher_no, rv.total_amount, rv.voucher_date
            ORDER BY rv.id ASC
        ");
        if (!is_array($receipt_vouchers)) {
            $receipt_vouchers = [];
        }
    }
    $receipt_voucher_rows = [];
    foreach ($receipt_vouchers as $v) {
        $amt = (float) $v['total_amount'];
        $receipt_voucher_rows[] = [
            'description' => day_report_prefix_doc_no('RV', $v['voucher_no'] ?? ''),
            'debit'       => 0,
            'credit'      => $amt,
            'inward_wt'   => (float) ($v['total_weight'] ?? 0),
            'outward_wt'  => 0,
        ];
    }

    if (day_report_tbl_exists($conn, 'tbl_sale_receipt_vouchers')) {
        $sale_receipt_vouchers = getList("
            SELECT srv.id, srv.voucher_no, srv.total_amount, srv.voucher_date,
                COALESCE(SUM(COALESCE(srvi.weight, 0)), 0) AS total_weight
            FROM tbl_sale_receipt_vouchers srv
            LEFT JOIN tbl_sale_receipt_voucher_items srvi ON srv.id = srvi.sale_receipt_voucher_id
            WHERE DATE(srv.voucher_date) = '$report_date' AND LOWER(TRIM(IFNULL(srv.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY srv.id, srv.voucher_no, srv.total_amount, srv.voucher_date
            ORDER BY srv.id ASC
        ");
        if (is_array($sale_receipt_vouchers)) {
            foreach ($sale_receipt_vouchers as $v) {
                $amt = (float) $v['total_amount'];
                $receipt_voucher_rows[] = [
                    'description' => day_report_prefix_doc_no('SRV', $v['voucher_no'] ?? ''),
                    'debit'       => 0,
                    'credit'      => $amt,
                    'inward_wt'   => (float) ($v['total_weight'] ?? 0),
                    'outward_wt'  => 0,
                ];
            }
        }
    }

    $advance_payments = [];
    if (day_report_tbl_exists($conn, 'tbl_advance_payments')) {
        $advance_payments = getList("
            SELECT ap.id, ap.voucher_no, ap.total_amount, ap.voucher_date,
                COALESCE(SUM(COALESCE(api.weight, 0)), 0) AS total_weight
            FROM tbl_advance_payments ap
            LEFT JOIN tbl_advance_payment_items api ON ap.id = api.voucher_id AND api.status = 1
            WHERE DATE(ap.voucher_date) = '$report_date' AND LOWER(TRIM(IFNULL(ap.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY ap.id, ap.voucher_no, ap.total_amount, ap.voucher_date
            ORDER BY ap.id ASC
        ");
        if (!is_array($advance_payments)) {
            $advance_payments = [];
        }
    }
    $advance_payment_rows = [];
    foreach ($advance_payments as $v) {
        $amt = (float) $v['total_amount'];
        $advance_payment_rows[] = [
            'description' => day_report_prefix_doc_no('AP', $v['voucher_no'] ?? ''),
            'debit'       => 0,
            'credit'      => $amt,
            'inward_wt'   => (float) ($v['total_weight'] ?? 0),
            'outward_wt'  => 0,
        ];
    }

    $purchase_invoice_rows = [];
    foreach ($purchase_invoices as $invoice) {
        $purchase_invoice_rows[] = [
            'description' => day_report_prefix_doc_no('PI', $invoice['invoice_no'] ?? ''),
            'debit'       => (float) $invoice['grand_total'],
            'credit'      => 0,
            'inward_wt'   => (float) $invoice['total_weight'],
            'outward_wt'  => 0,
        ];
    }

    $sale_order_rows = [];
    foreach ($sale_orders as $order) {
        $sale_order_rows[] = [
            'description' => day_report_prefix_doc_no('SO', $order['order_no'] ?? ''),
            'debit'       => 0,
            'credit'      => (float) $order['grand_total'],
            'inward_wt'   => 0,
            'outward_wt'  => (float) $order['total_weight'],
        ];
    }

    $repair_orders = [];
    if (day_report_tbl_exists($conn, 'tbl_repair_orders')) {
        $repair_orders = getList("
            SELECT ro.id, ro.order_no, ro.grand_total, ro.order_date,
                COALESCE(SUM(roi.final_weight), 0) AS total_weight
            FROM tbl_repair_orders ro
            LEFT JOIN tbl_repair_order_items roi ON ro.id = roi.order_id
            WHERE DATE(ro.order_date) = '$report_date' AND LOWER(TRIM(IFNULL(ro.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
            GROUP BY ro.id, ro.order_no, ro.grand_total, ro.order_date
            ORDER BY ro.id ASC
        ");
        if (!is_array($repair_orders)) {
            $repair_orders = [];
        }
    }
    $repair_order_rows = [];
    foreach ($repair_orders as $ro) {
        $repair_order_rows[] = [
            'description' => day_report_prefix_doc_no('RO', $ro['order_no'] ?? ''),
            'debit'       => 0,
            'credit'      => (float) $ro['grand_total'],
            'inward_wt'   => 0,
            'outward_wt'  => (float) $ro['total_weight'],
        ];
    }

    $bank_accounts_raw = getList("SELECT id, name FROM tbl_customers WHERE sundry_debtors_id = 29 AND status = 1 AND TRIM(IFNULL(name,'')) != '' ORDER BY name ASC");
    if (!is_array($bank_accounts_raw)) {
        $bank_accounts_raw = [];
    }
    $exclude_names = ['phonepe', 'phonepay', 'gpay', 'google pay', 'paytm', 'upi', '0.00', '0'];
    $bank_accounts   = [];
    foreach ($bank_accounts_raw as $b) {
        $n = trim(strtolower($b['name'] ?? ''));
        if ($n === '' || in_array($n, $exclude_names, true) || preg_match('/^[0-9.]+$/', $n)) {
            continue;
        }
        $bank_accounts[] = $b;
    }

    $bank_rows = [];
    foreach ($bank_accounts as $b) {
        [$bc, $bd] = day_report_bank_day_totals($conn, $report_date, $b);
        $bank_rows[] = [
            'description' => trim((string) ($b['name'] ?? '')),
            'debit'       => $bd,
            'credit'      => $bc,
            'inward_wt'   => 0,
            'outward_wt'  => 0,
        ];
    }

    $sum_rows = static function (array $rows): array {
        $d = $c = $iw = $ow = 0.0;
        foreach ($rows as $r) {
            $d  += (float) ($r['debit'] ?? 0);
            $c  += (float) ($r['credit'] ?? 0);
            $iw += (float) ($r['inward_wt'] ?? 0);
            $ow += (float) ($r['outward_wt'] ?? 0);
        }

        return ['debit' => $d, 'credit' => $c, 'inward_wt' => $iw, 'outward_wt' => $ow];
    };

    $sections = [
        [
            'key'    => 'sale_invoice',
            'label'  => 'Sale Invoice',
            'totals' => $sum_rows($sale_invoice_rows),
            'rows'   => $sale_invoice_rows,
        ],
        [
            'key'    => 'pos_sale_invoice',
            'label'  => 'POS Sale Invoice',
            'totals' => $sum_rows($pos_rows),
            'rows'   => $pos_rows,
        ],
        [
            'key'    => 'sale_quotation',
            'label'  => 'Sale Quotation',
            'totals' => $sum_rows($sale_quotation_rows),
            'rows'   => $sale_quotation_rows,
        ],
        [
            'key'    => 'sale_return',
            'label'  => 'Sale Return',
            'totals' => $sum_rows($sale_return_rows),
            'rows'   => $sale_return_rows,
        ],
        [
            'key'    => 'purchase_invoice',
            'label'  => 'Purchase Invoice',
            'totals' => $sum_rows($purchase_invoice_rows),
            'rows'   => $purchase_invoice_rows,
        ],
        [
            'key'    => 'purchase_quotation',
            'label'  => 'Purchase Quotation',
            'totals' => $sum_rows($purchase_quotation_rows),
            'rows'   => $purchase_quotation_rows,
        ],
        [
            'key'    => 'purchase_return',
            'label'  => 'Purchase Return',
            'totals' => $sum_rows($purchase_return_rows),
            'rows'   => $purchase_return_rows,
        ],
        [
            'key'    => 'old_jewelry_scrap',
            'label'  => 'Old Jewellery - Scrap Invoice',
            'totals' => $sum_rows($scrap_rows),
            'rows'   => $scrap_rows,
        ],
        [
            'key'    => 'payment_voucher',
            'label'  => 'Payment Voucher',
            'totals' => $sum_rows($payment_voucher_rows),
            'rows'   => $payment_voucher_rows,
        ],
        [
            'key'    => 'receipt_voucher',
            'label'  => 'Receipt Voucher',
            'totals' => $sum_rows($receipt_voucher_rows),
            'rows'   => $receipt_voucher_rows,
        ],
        [
            'key'    => 'advance_payment',
            'label'  => 'Advance Payment',
            'totals' => $sum_rows($advance_payment_rows),
            'rows'   => $advance_payment_rows,
        ],
        [
            'key'    => 'sale_order',
            'label'  => 'Sale Order',
            'totals' => $sum_rows($sale_order_rows),
            'rows'   => $sale_order_rows,
        ],
        [
            'key'    => 'repair_order',
            'label'  => 'Repair Order',
            'totals' => $sum_rows($repair_order_rows),
            'rows'   => $repair_order_rows,
        ],
        [
            'key'    => 'bank',
            'label'  => 'Bank',
            'totals' => $sum_rows($bank_rows),
            'rows'   => $bank_rows,
        ],
    ];

    $total_debit = $total_credit = $total_inward_wt = $total_outward_wt = 0.0;
    $total_cash = $total_online_cheque = 0.0;

    foreach ($sale_invoices as $invoice) {
        $total_credit        += (float) $invoice['grand_total'];
        $total_cash          += (float) $invoice['cash_amount'];
        $total_online_cheque += (float) $invoice['online_cheque_amount'];
        $total_outward_wt    += (float) $invoice['total_weight'];
    }

    foreach ($pos_invoices as $invoice) {
        $total_credit        += (float) $invoice['grand_total'];
        $total_cash          += (float) $invoice['cash_amount'];
        $total_online_cheque += (float) $invoice['online_cheque_amount'];
        $total_outward_wt    += (float) $invoice['total_weight'];
    }

    foreach ($sale_orders as $order) {
        $total_credit        += (float) $order['grand_total'];
        $total_cash          += (float) $order['cash_amount'];
        $total_online_cheque += (float) $order['online_cheque_amount'];
        $total_outward_wt    += (float) $order['total_weight'];
    }

    foreach ($purchase_invoices as $invoice) {
        $total_debit           += (float) $invoice['grand_total'];
        $total_cash            -= (float) $invoice['cash_amount'];
        $total_online_cheque   -= (float) $invoice['online_cheque_amount'];
        $total_inward_wt       += (float) $invoice['total_weight'];
    }

    foreach ($sale_returns as $r) {
        $total_debit     += (float) $r['grand_total'];
        $total_inward_wt += (float) $r['total_weight'];
    }

    foreach ($purchase_returns as $r) {
        $total_credit     += (float) $r['grand_total'];
        $total_outward_wt += (float) $r['total_weight'];
    }

    foreach ($scrap_invoices as $r) {
        $total_debit     += (float) $r['grand_total'];
        $total_inward_wt += (float) $r['total_weight'];
    }

    foreach ($payment_vouchers as $v) {
        $total_debit += (float) $v['total_amount'];
    }

    foreach ($receipt_vouchers as $v) {
        $total_credit += (float) $v['total_amount'];
    }

    foreach ($advance_payments as $v) {
        $total_credit += (float) $v['total_amount'];
    }

    foreach ($repair_orders as $ro) {
        $total_credit     += (float) $ro['grand_total'];
        $total_outward_wt += (float) $ro['total_weight'];
    }

    $transactions = [];
    foreach ($sections as $sec) {
        foreach ($sec['rows'] as $r) {
            $transactions[] = array_merge(['section' => $sec['key']], $r);
        }
    }

    $expected_amount   = $total_credit - $total_debit;
    $closing_cash      = $opening_amount + $total_cash;
    $cash_denomination = abs($total_cash);
    $difference        = $closing_cash - $cash_denomination;

    return [
        'sections'     => $sections,
        'transactions' => $transactions,
        'summary'      => [
            'opening_amount'        => $opening_amount,
            'expected_amount'       => $expected_amount,
            'online_cheque_payment' => $total_online_cheque,
            'closing_cash'          => $closing_cash,
            'cash_denomination'     => $cash_denomination,
            'difference'            => $difference,
        ],
    ];
}

if (!function_exists('day_report_prefix_doc_no')) {
    /**
     * @param string $prefix Upper prefix without dash, e.g. 'SI', 'SO', 'PI'
     */
    function day_report_prefix_doc_no($prefix, $invoice_no)
    {
        $no = trim((string) $invoice_no);
        if ($no === '') {
            return $prefix . '-';
        }
        $re = '/^' . preg_quote($prefix, '/') . '-?/i';
        $rest = preg_replace($re, '', $no, 1);

        return $prefix . '-' . ltrim($rest, '-');
    }
}

if (!function_exists('day_report_tbl_exists')) {
    function day_report_tbl_exists($conn, $table)
    {
        $t = mysqli_real_escape_string($conn, $table);
        $q = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
        if ($q && mysqli_num_rows($q) > 0) {
            mysqli_free_result($q);

            return true;
        }

        return false;
    }
}

if (!function_exists('day_report_bank_day_totals')) {
    /**
     * @return array{0: float, 1: float} [ credit_total, debit_total ]
     */
    function day_report_bank_day_totals($conn, $report_date, array $bank)
    {
        $id   = (int) ($bank['id'] ?? 0);
        $name = trim((string) ($bank['name'] ?? ''));
        $name_esc = mysqli_real_escape_string($conn, $name);
        $id_str   = (string) $id;

        $credit = 0.0;
        $debit  = 0.0;

        $q1 = "
        SELECT COALESCE(SUM(sip.amount), 0) AS amt
        FROM tbl_sale_invoice_payments sip
        INNER JOIN tbl_sale_invoices si ON sip.invoice_id = si.id
        WHERE DATE(si.invoice_date) = '$report_date'
          AND sip.status = 1
          AND sip.payment_type IN ('bank', 'cheque', 'upi', 'card')
          AND LOWER(TRIM(IFNULL(si.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
          AND (
              TRIM(IFNULL(sip.deposit_into,'')) = '$name_esc'
              OR TRIM(IFNULL(sip.deposit_into,'')) = '$id_str'
          )
    ";
        $row = getRecord($q1);
        $credit += (float) ($row['amt'] ?? 0);

        $q2 = "
        SELECT COALESCE(SUM(sop.amount), 0) AS amt
        FROM tbl_sale_order_payments sop
        INNER JOIN tbl_sale_orders so ON sop.order_id = so.id
        WHERE DATE(so.order_date) = '$report_date'
          AND sop.status = 1
          AND sop.payment_type IN ('bank', 'cheque', 'upi', 'card')
          AND LOWER(TRIM(IFNULL(so.status, ''))) NOT IN ('draft', 'cancelled', 'void', 'deleted')
          AND (
              TRIM(IFNULL(sop.deposit_into,'')) = '$name_esc'
              OR TRIM(IFNULL(sop.deposit_into,'')) = '$id_str'
          )
    ";
        $row = getRecord($q2);
        $credit += (float) ($row['amt'] ?? 0);

        $q3 = "
        SELECT COALESCE(SUM(pip.amount), 0) AS amt
        FROM tbl_purchase_invoice_payments pip
        INNER JOIN tbl_purchase_invoices pi ON pip.invoice_id = pi.id
        WHERE DATE(pi.invoice_date) = '$report_date'
          AND pip.status = 1
          AND pip.payment_type IN ('bank', 'cheque', 'upi', 'card')
          AND LOWER(TRIM(IFNULL(pi.status, ''))) NOT IN ('cancelled', 'void', 'deleted')
          AND (
              TRIM(IFNULL(pip.deposit_into,'')) = '$name_esc'
              OR TRIM(IFNULL(pip.deposit_into,'')) = '$id_str'
          )
    ";
        $row = getRecord($q3);
        $debit += (float) ($row['amt'] ?? 0);

        return [$credit, $debit];
    }
}
