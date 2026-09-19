<?php
/**
 * When a sale invoice has a scrap payment, ensure a linked Old Jewellery Scrap (OJB) invoice exists.
 * ref_no = SI:{sale_invoice_id} — against_of = sale invoice no (e.g. SPK11). Reuses helpers from sync_purchase_scrap_to_ojb.php.
 */
require_once __DIR__ . '/sync_purchase_scrap_to_ojb.php';

if (!function_exists('syncSaleScrapToOjb')) {
    function syncSaleScrapToOjb($conn, $sale_invoice_id)
    {
        $sale_invoice_id = (int) $sale_invoice_id;
        if ($sale_invoice_id <= 0) {
            return;
        }

        $t1 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
        $t2 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_items'");
        if (!$t1 || mysqli_num_rows($t1) === 0 || !$t2 || mysqli_num_rows($t2) === 0) {
            if ($t1) {
                mysqli_free_result($t1);
            }
            if ($t2) {
                mysqli_free_result($t2);
            }

            return;
        }
        mysqli_free_result($t1);
        mysqli_free_result($t2);

        $ref = 'SI:' . $sale_invoice_id;
        $ref_esc = mysqli_real_escape_string($conn, $ref);

        $refCol = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoices LIKE 'ref_no'");
        if (!$refCol || mysqli_num_rows($refCol) === 0) {
            @mysqli_query($conn, "ALTER TABLE tbl_old_jewelry_scrap_invoices ADD COLUMN ref_no VARCHAR(100) NULL DEFAULT NULL AFTER against_of");
        }
        if ($refCol) {
            mysqli_free_result($refCol);
        }

        $exists = getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoices WHERE ref_no = '$ref_esc' LIMIT 1");
        if ($exists && !empty($exists['id'])) {
            $ojb_existing_id = (int) $exists['id'];
            $scrap_pay_still = getRecord("SELECT id, payment_details, amount FROM tbl_sale_invoice_payments WHERE invoice_id = $sale_invoice_id AND LOWER(TRIM(payment_type)) = 'scrap' AND status = 1 ORDER BY id DESC LIMIT 1");
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
            if ($row && trim((string) ($row['against_of'] ?? '')) === '' && strpos((string) ($row['ref_no'] ?? ''), 'SI:') === 0) {
                $si_fix = getRecord("SELECT invoice_no FROM tbl_sale_invoices WHERE id = $sale_invoice_id LIMIT 1");
                if ($si_fix && trim((string) ($si_fix['invoice_no'] ?? '')) !== '') {
                    $ino = esc($si_fix['invoice_no']);
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

        $si = getRecord("SELECT * FROM tbl_sale_invoices WHERE id = $sale_invoice_id LIMIT 1");
        if (!$si) {
            return;
        }

        $scrap_pay = getRecord("SELECT * FROM tbl_sale_invoice_payments WHERE invoice_id = $sale_invoice_id AND LOWER(TRIM(payment_type)) = 'scrap' AND status = 1 ORDER BY id DESC LIMIT 1");
        if (!$scrap_pay) {
            return;
        }

        $scrap_pay_amt = (float) ($scrap_pay['amount'] ?? 0);

        $items = getList("SELECT sii.* FROM tbl_sale_invoice_items sii WHERE sii.invoice_id = $sale_invoice_id AND IFNULL(sii.status, 1) = 1");
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
        $customer_name_esc = esc($si['customer_name'] ?? '');
        $against_raw = trim((string) ($si['invoice_no'] ?? ''));
        $against_esc = esc($against_raw);
        $inv_date = esc($si['invoice_date'] ?? date('Y-m-d'));
        $grand_total = $scrap_pay_amt > 0 ? $scrap_pay_amt : (float) ($si['grand_total'] ?? 0);
        $created_by = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : 0;
        $sales_person_esc = esc($si['sales_person'] ?? '');

        $has_sp = false;
        $hc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoices LIKE 'sales_person'");
        if ($hc && mysqli_num_rows($hc) > 0) {
            $has_sp = true;
        }
        if ($hc) {
            mysqli_free_result($hc);
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

        foreach ($items as $sii) {
            $metal_id = 0;
            $pcid = isset($sii['product_characteristic_id']) ? (int) $sii['product_characteristic_id'] : 0;
            if ($pcid > 0) {
                $pc = getRecord("SELECT metal_id FROM tbl_product_characteristics WHERE id = $pcid LIMIT 1");
                if ($pc) {
                    $metal_id = (int) ($pc['metal_id'] ?? 0);
                }
            }
            $pn_esc = esc($sii['product_name'] ?? '');
            $gross_wt = (float) ($sii['gross_weight'] ?? 0);
            $less_wt = (float) ($sii['less_weight'] ?? 0);
            $net_wt = (float) ($sii['net_weight'] ?? 0);
            $final_wt = (float) ($sii['final_weight'] ?? 0);
            $purity = (float) ($sii['purity'] ?? 0);
            $purity_wt = (float) ($sii['pure_weight'] ?? $sii['purity_weight'] ?? 0);
            $rate = (float) ($sii['rate'] ?? 0);
            $amount = (float) ($sii['amount'] ?? 0);
            $quantity = 1.0;
            $barcode_esc = esc($sii['barcode'] ?? '');
            $stone_wt = (float) ($sii['stone_weight'] ?? 0);

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
}

if (!function_exists('syncSaleScrapToOjbForDateRange')) {
    function syncSaleScrapToOjbForDateRange($conn, $from_date, $to_date)
    {
        if (empty($from_date) || empty($to_date)) {
            return;
        }
        $fd = esc($from_date);
        $td = esc($to_date);
        $rows = getList("SELECT DISTINCT si.id FROM tbl_sale_invoices si WHERE DATE(si.invoice_date) BETWEEN '$fd' AND '$td' AND EXISTS (
            SELECT 1 FROM tbl_sale_invoice_payments sip WHERE sip.invoice_id = si.id AND LOWER(TRIM(sip.payment_type)) = 'scrap' AND sip.status = 1
        )");
        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as $r) {
            syncSaleScrapToOjb($conn, (int) ($r['id'] ?? 0));
        }
    }
}
