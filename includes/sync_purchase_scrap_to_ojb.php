<?php
/**
 * When a purchase invoice has a scrap payment, ensure a linked Old Jewellery Scrap (OJB) invoice exists.
 * ref_no = PI:{purchase_invoice_id} — Invoice No. on Old Jewellery list is OJB-xxx; against_of = purchase invoice no (e.g. PRI3).
 */
if (!function_exists('pipTableHasPaymentDetailsColumn')) {
    /** True when tbl_purchase_invoice_payments has payment_details (JSON); avoids mysqli_sql_exception on SELECT. */
    function pipTableHasPaymentDetailsColumn($conn)
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_payments LIKE 'payment_details'");
        $cached = ($r && mysqli_num_rows($r) > 0);
        if ($r) {
            mysqli_free_result($r);
        }

        return $cached;
    }
}

if (!function_exists('ojbGetOjbItemColumnFlags')) {
    function ojbGetOjbItemColumnFlags($conn)
    {
        $has_metal_id = false;
        $has_less_wt = false;
        $cols = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_items');
        if ($cols) {
            while ($c = mysqli_fetch_assoc($cols)) {
                if (($c['Field'] ?? '') === 'metal_id') {
                    $has_metal_id = true;
                }
                if (($c['Field'] ?? '') === 'less_wt') {
                    $has_less_wt = true;
                }
            }
        }

        return [$has_metal_id, $has_less_wt];
    }
}

if (!function_exists('ojbScrapPaymentDetailsHasModal')) {
    function ojbScrapPaymentDetailsHasModal($pd)
    {
        if (!is_array($pd)) {
            return false;
        }

        $amt = (float) ($pd['amount'] ?? 0);
        if ($amt <= 0.00001) {
            $amt = (float) ($pd['current_order_amount'] ?? 0);
        }

        return (
            (float) ($pd['scrap_gross_wt'] ?? 0) > 0
            || (float) ($pd['scrap_net_wt'] ?? 0) > 0
            || $amt > 0.00001
        );
    }
}

if (!function_exists('ojbBuildScrapModalItemValuesFromPaymentDetails')) {
    /**
     * Maps purchase-invoice scrap payment JSON to OJB item column values.
     *
     * @return array<string, mixed>|null  null if JSON does not look like scrap modal data
     */
    function ojbBuildScrapModalItemValuesFromPaymentDetails($pd)
    {
        if (!ojbScrapPaymentDetailsHasModal($pd)) {
            return null;
        }
        $sg = (float) ($pd['scrap_gross_wt'] ?? 0);
        $sl = (float) ($pd['scrap_less_wt'] ?? 0);
        $ss = (float) ($pd['scrap_stone_wt'] ?? 0);
        $sn = (float) ($pd['scrap_net_wt'] ?? 0);
        if ($sn <= 0 && ($sg > 0 || $sl > 0 || $ss > 0)) {
            $sn = max(0, $sg - $sl - $ss);
        }
        $purity = (float) ($pd['purity_carat'] ?? 0);
        if ($purity <= 0) {
            $purity = (float) ($pd['purity'] ?? 0);
        }
        $rate = (float) ($pd['scrap_rate'] ?? 0);
        $amount = (float) ($pd['amount'] ?? 0);
        if ($amount <= 0.00001) {
            $amount = (float) ($pd['current_order_amount'] ?? 0);
        }
        $quantity = (float) ($pd['quantity'] ?? 1);
        if ($quantity <= 0) {
            $quantity = 1;
        }
        $metal_id = (int) ($pd['scrap_metal_id'] ?? 0);
        $pure_wt = (float) ($pd['scrap_purity_wt'] ?? 0);
        if ($pure_wt <= 0 && $sn > 0 && $purity > 0) {
            $pure_wt = $sn * $purity;
        }
        $final_wt = $pure_wt;
        if ($final_wt <= 0 && $sn > 0 && $purity > 0) {
            $final_wt = $sn * $purity;
        }
        if ($final_wt <= 0) {
            $final_wt = $sn > 0 ? $sn : $sg;
        }
        if ($amount <= 0 && $rate > 0 && $sn > 0) {
            $amount = $rate * $sn;
        }
        if ($amount <= 0 && $rate > 0 && $sg > 0) {
            $amount = $rate * $sg;
        }

        return [
            'gross_wt' => $sg,
            'less_wt' => $sl,
            'final_wt' => $final_wt,
            'net_wt' => $sn,
            'pure_wt' => $pure_wt,
            'quantity' => $quantity,
            'purity' => $purity,
            'rate' => $rate,
            'amount' => $amount,
            'description' => trim((string) ($pd['scrap_product_name'] ?? '')),
            'barcode' => trim((string) ($pd['scrap_item_code'] ?? '')),
            'metal_id' => $metal_id,
            'gemstone_wt' => $ss,
        ];
    }
}

if (!function_exists('ojbInsertScrapModalItemFromPaymentDetails')) {
    function ojbInsertScrapModalItemFromPaymentDetails($conn, $ojb_id, $pd, $has_metal_id, $has_less_wt)
    {
        $vals = ojbBuildScrapModalItemValuesFromPaymentDetails($pd);
        if ($vals === null) {
            return false;
        }
        $sg = $vals['gross_wt'];
        $sl = $vals['less_wt'];
        $ss = $vals['gemstone_wt'];
        $sn = $vals['net_wt'];
        $purity = $vals['purity'];
        $rate = $vals['rate'];
        $amount = $vals['amount'];
        $quantity = $vals['quantity'];
        $metal_id = (int) $vals['metal_id'];
        $pn_esc = esc($vals['description']);
        $barcode_esc = esc($vals['barcode']);
        $pure_wt = $vals['pure_wt'];
        $final_wt = $vals['final_wt'];

        if ($has_metal_id && $has_less_wt) {
            $mi = $metal_id > 0 ? $metal_id : 'NULL';
            $qi = "INSERT INTO tbl_old_jewelry_scrap_invoice_items (invoice_id, metal_id, barcode, description, gross_wt, less_wt, final_wt, net_wt, pure_wt, quantity, purity, rate, amount, diamond_wt, gemstone_wt, status) VALUES (
                $ojb_id, $mi, " . ($barcode_esc !== '' ? "'$barcode_esc'" : 'NULL') . ', ' . ($pn_esc !== '' ? "'$pn_esc'" : 'NULL') . ",
                $sg, $sl, $final_wt, $sn, $pure_wt, $quantity, $purity, $rate, $amount, 0, $ss, 1
            )";
        } else {
            $qi = "INSERT INTO tbl_old_jewelry_scrap_invoice_items (invoice_id, barcode, description, gross_wt, final_wt, net_wt, pure_wt, quantity, purity, rate, amount, diamond_wt, gemstone_wt, status) VALUES (
                $ojb_id, " . ($barcode_esc !== '' ? "'$barcode_esc'" : 'NULL') . ', ' . ($pn_esc !== '' ? "'$pn_esc'" : 'NULL') . ",
                $sg, $final_wt, $sn, $pure_wt, $quantity, $purity, $rate, $amount, 0, $ss, 1
            )";
        }

        return (bool) mysqli_query($conn, $qi);
    }
}

if (!function_exists('syncPurchaseScrapToOjb')) {
    function syncPurchaseScrapToOjb($conn, $purchase_invoice_id)
    {
        $purchase_invoice_id = (int) $purchase_invoice_id;
        if ($purchase_invoice_id <= 0) {
            return;
        }

        $t1 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
        $t2 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_items'");
        if (!$t1 || mysqli_num_rows($t1) === 0 || !$t2 || mysqli_num_rows($t2) === 0) {
            return;
        }

        $ref = 'PI:' . $purchase_invoice_id;
        $ref_esc = mysqli_real_escape_string($conn, $ref);

        $refCol = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoices LIKE 'ref_no'");
        if (!$refCol || mysqli_num_rows($refCol) === 0) {
            @mysqli_query($conn, "ALTER TABLE tbl_old_jewelry_scrap_invoices ADD COLUMN ref_no VARCHAR(100) NULL DEFAULT NULL AFTER against_of");
        }

        $exists = getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoices WHERE ref_no = '$ref_esc' LIMIT 1");
        if ($exists && !empty($exists['id'])) {
            $ojb_existing_id = (int) $exists['id'];
            $scrap_pay_still = getRecord("SELECT id, payment_details, amount FROM tbl_purchase_invoice_payments WHERE invoice_id = $purchase_invoice_id AND LOWER(TRIM(payment_type)) = 'scrap' AND status = 1 ORDER BY id DESC LIMIT 1");
            if (!$scrap_pay_still) {
                mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE transaction_id = $ojb_existing_id AND transaction_type IN ('old_jewelry_scrap_invoice', 'old_jewelry_scrap_contra') AND status = 1");
                $ojb_meta = getRecord("SELECT invoice_no, customer_name FROM tbl_old_jewelry_scrap_invoices WHERE id = $ojb_existing_id LIMIT 1");
                if ($ojb_meta) {
                    $ino_m = esc(trim((string) ($ojb_meta['invoice_no'] ?? '')));
                    $cn_m = esc(trim((string) ($ojb_meta['customer_name'] ?? '')));
                    if ($ino_m !== '' && $cn_m !== '') {
                        mysqli_query($conn, "DELETE FROM tbl_customer_ledger WHERE status = 1 AND customer_name = '$cn_m' AND transaction_no = '$ino_m' AND transaction_type IN ('old_jewelry_scrap_invoice', 'Old Jewelry - Scrap Invoice')");
                    }
                }
                mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoices WHERE id = $ojb_existing_id");

                return;
            }
            $row = getRecord("SELECT id, against_of, ref_no FROM tbl_old_jewelry_scrap_invoices WHERE id = $ojb_existing_id LIMIT 1");
            if ($row && trim((string) ($row['against_of'] ?? '')) === '' && strpos((string) ($row['ref_no'] ?? ''), 'PI:') === 0) {
                $pi_fix = getRecord("SELECT invoice_no FROM tbl_purchase_invoices WHERE id = $purchase_invoice_id LIMIT 1");
                if ($pi_fix && trim((string) ($pi_fix['invoice_no'] ?? '')) !== '') {
                    $ino = esc($pi_fix['invoice_no']);
                    mysqli_query($conn, "UPDATE tbl_old_jewelry_scrap_invoices SET against_of = '$ino' WHERE id = " . (int) $row['id']);
                }
            }
            $scrap_pay_existing = $scrap_pay_still;
            $pd_existing = [];
            if ($scrap_pay_existing && !empty($scrap_pay_existing['payment_details'])) {
                $pd_existing = json_decode($scrap_pay_existing['payment_details'], true);
            }
            if (!is_array($pd_existing)) {
                $pd_existing = [];
            }
            if (ojbScrapPaymentDetailsHasModal($pd_existing)) {
                list($has_metal_id_ex, $has_less_wt_ex) = ojbGetOjbItemColumnFlags($conn);
                $old_rows = getList("SELECT id FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = $ojb_existing_id ORDER BY id ASC");
                $old_ids = [];
                foreach ($old_rows ?: [] as $or) {
                    $old_ids[] = (int) ($or['id'] ?? 0);
                }
                mysqli_query($conn, "DELETE FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = $ojb_existing_id");
                ojbInsertScrapModalItemFromPaymentDetails($conn, $ojb_existing_id, $pd_existing, $has_metal_id_ex, $has_less_wt_ex);
                $new_rows = getList("SELECT id FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = $ojb_existing_id ORDER BY id ASC");
                $new_ids = [];
                foreach ($new_rows ?: [] as $nr) {
                    $new_ids[] = (int) ($nr['id'] ?? 0);
                }
                require_once __DIR__ . '/old_jewelry_scrap_stock_balance.php';
                if (function_exists('auragold_oj_scrap_remap_stock_after_invoice_items_replaced')) {
                    auragold_oj_scrap_remap_stock_after_invoice_items_replaced($conn, $ojb_existing_id, $old_ids, $new_ids);
                }
            }
            $sam_up = (float) ($scrap_pay_still['amount'] ?? 0);
            if ($sam_up > 0) {
                mysqli_query($conn, "UPDATE tbl_old_jewelry_scrap_invoices SET grand_total = $sam_up, net_total = $sam_up, subtotal = $sam_up, paid_amt = $sam_up, balance_amt = 0 WHERE id = $ojb_existing_id");
            }

            return;
        }

        $pi = getRecord("SELECT * FROM tbl_purchase_invoices WHERE id = $purchase_invoice_id LIMIT 1");
        if (!$pi) {
            return;
        }

        $scrap_pay = getRecord("SELECT * FROM tbl_purchase_invoice_payments WHERE invoice_id = $purchase_invoice_id AND LOWER(TRIM(payment_type)) = 'scrap' AND status = 1 ORDER BY id DESC LIMIT 1");
        if (!$scrap_pay) {
            return;
        }

        $scrap_pay_amt = (float) ($scrap_pay['amount'] ?? 0);

        $pii_active_sql = 'IFNULL(pii.status, 1) = 1';
        $pac = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'active'");
        if ($pac && mysqli_num_rows($pac) > 0) {
            $pii_active_sql = 'IFNULL(pii.active, 1) = 1';
        }

        $items = getList("SELECT pii.* FROM tbl_purchase_invoice_items pii WHERE pii.invoice_id = $purchase_invoice_id AND $pii_active_sql");
        if (!is_array($items)) {
            $items = [];
        }

        $pd = [];
        if (!empty($scrap_pay['payment_details'])) {
            $pd = json_decode($scrap_pay['payment_details'], true);
        }
        if (!is_array($pd)) {
            $pd = [];
        }
        $use_scrap_modal = ojbScrapPaymentDetailsHasModal($pd);
        if (count($items) === 0 && !$use_scrap_modal) {
            return;
        }

        $last = getRecord("SELECT invoice_no FROM tbl_old_jewelry_scrap_invoices ORDER BY id DESC LIMIT 1");
        $next_num = 1;
        if ($last && !empty($last['invoice_no'])) {
            $next_num = (int) preg_replace('/[^0-9]/', '', $last['invoice_no']) + 1;
        }
        $invoice_no = 'OJB-' . $next_num;
        $invoice_no_esc = esc($invoice_no);
        $customer_name_esc = esc($pi['supplier_name'] ?? '');
        $against_raw = trim((string) ($pi['invoice_no'] ?? ''));
        $against_esc = esc($against_raw);
        $inv_date = esc($pi['invoice_date'] ?? date('Y-m-d'));
        // Header must reflect scrap settlement, not the full purchase invoice total
        $grand_total = $scrap_pay_amt > 0 ? $scrap_pay_amt : (float) ($pi['grand_total'] ?? 0);
        $created_by = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : 0;
        $sales_person_esc = esc($pi['purchase_person'] ?? '');

        $has_sp = false;
        $hc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoices LIKE 'sales_person'");
        if ($hc && mysqli_num_rows($hc) > 0) {
            $has_sp = true;
        }

        if ($has_sp) {
            $ins_inv = "INSERT INTO tbl_old_jewelry_scrap_invoices (
                invoice_no, customer_name, against_of, ref_no, invoice_date, grand_total, status, sales_person, created_by
            ) VALUES (
                '$invoice_no_esc', '$customer_name_esc', " . ($against_esc !== '' ? "'$against_esc'" : 'NULL') . ",
                '$ref_esc', '$inv_date', $grand_total, 'saved', " . ($sales_person_esc !== '' ? "'$sales_person_esc'" : 'NULL') . ", $created_by
            )";
        } else {
            $ins_inv = "INSERT INTO tbl_old_jewelry_scrap_invoices (
                invoice_no, customer_name, against_of, ref_no, invoice_date, grand_total, status, created_by
            ) VALUES (
                '$invoice_no_esc', '$customer_name_esc', " . ($against_esc !== '' ? "'$against_esc'" : 'NULL') . ",
                '$ref_esc', '$inv_date', $grand_total, 'saved', $created_by
            )";
        }

        if (!mysqli_query($conn, $ins_inv)) {
            return;
        }
        $ojb_id = (int) mysqli_insert_id($conn);

        list($has_metal_id, $has_less_wt) = ojbGetOjbItemColumnFlags($conn);

        if ($use_scrap_modal) {
            ojbInsertScrapModalItemFromPaymentDetails($conn, $ojb_id, $pd, $has_metal_id, $has_less_wt);
            if ($scrap_pay_amt > 0) {
                mysqli_query($conn, "UPDATE tbl_old_jewelry_scrap_invoices SET grand_total = $scrap_pay_amt, net_total = $scrap_pay_amt, subtotal = $scrap_pay_amt, paid_amt = $scrap_pay_amt, balance_amt = 0 WHERE id = $ojb_id");
            }

            return;
        }

        foreach ($items as $pii) {
            $metal_id = 0;
            $pcid = isset($pii['product_characteristic_id']) ? (int) $pii['product_characteristic_id'] : 0;
            if ($pcid > 0) {
                $pc = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE id = $pcid LIMIT 1");
                if ($pc) {
                    $metal_id = (int) ($pc['metal_id'] ?? 0);
                }
            }
            $pn_esc = esc($pii['product_name'] ?? '');
            $gross_wt = (float) ($pii['gross_weight'] ?? 0);
            $less_wt = (float) ($pii['less_weight'] ?? 0);
            $net_wt = (float) ($pii['net_weight'] ?? 0);
            $final_wt = (float) ($pii['final_weight'] ?? 0);
            $purity = (float) ($pii['purity'] ?? 0);
            $purity_wt = (float) ($pii['purity_weight'] ?? 0);
            $rate = (float) ($pii['rate'] ?? 0);
            $amount = (float) ($pii['amount'] ?? 0);
            $quantity = 1.0;
            $barcode_esc = esc($pii['barcode'] ?? '');
            $stone_wt = (float) ($pii['stone_weight'] ?? 0);

            if ($has_metal_id && $has_less_wt) {
                $mi = $metal_id > 0 ? $metal_id : 'NULL';
                $qi = "INSERT INTO tbl_old_jewelry_scrap_invoice_items (invoice_id, metal_id, barcode, description, gross_wt, less_wt, final_wt, net_wt, pure_wt, quantity, purity, rate, amount, diamond_wt, gemstone_wt, status) VALUES (
                    $ojb_id, $mi, " . ($barcode_esc !== '' ? "'$barcode_esc'" : 'NULL') . ', ' . ($pn_esc !== '' ? "'$pn_esc'" : 'NULL') . ",
                    $gross_wt, $less_wt, $final_wt, $net_wt, $purity_wt, $quantity, $purity, $rate, $amount, 0, $stone_wt, 1
                )";
            } else {
                $qi = "INSERT INTO tbl_old_jewelry_scrap_invoice_items (invoice_id, barcode, description, gross_wt, final_wt, net_wt, pure_wt, quantity, purity, rate, amount, diamond_wt, gemstone_wt, status) VALUES (
                    $ojb_id, " . ($barcode_esc !== '' ? "'$barcode_esc'" : 'NULL') . ', ' . ($pn_esc !== '' ? "'$pn_esc'" : 'NULL') . ",
                    $gross_wt, $final_wt, $net_wt, $purity_wt, $quantity, $purity, $rate, $amount, 0, $stone_wt, 1
                )";
            }
            mysqli_query($conn, $qi);
        }

        if ($scrap_pay_amt > 0) {
            mysqli_query($conn, "UPDATE tbl_old_jewelry_scrap_invoices SET grand_total = $scrap_pay_amt, net_total = $scrap_pay_amt, subtotal = $scrap_pay_amt, paid_amt = $scrap_pay_amt, balance_amt = 0 WHERE id = $ojb_id");
        }
    }

    function syncPurchaseScrapToOjbForDateRange($conn, $from_date, $to_date)
    {
        if (empty($from_date) || empty($to_date)) {
            return;
        }
        $fd = esc($from_date);
        $td = esc($to_date);
        $rows = getList("SELECT DISTINCT pi.id FROM tbl_purchase_invoices pi WHERE DATE(pi.invoice_date) BETWEEN '$fd' AND '$td' AND EXISTS (
            SELECT 1 FROM tbl_purchase_invoice_payments pip WHERE pip.invoice_id = pi.id AND LOWER(TRIM(pip.payment_type)) = 'scrap' AND pip.status = 1
        )");
        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as $r) {
            syncPurchaseScrapToOjb($conn, (int) ($r['id'] ?? 0));
        }
    }
}
