<?php
/**
 * Detect OJB / sale-order rows that represent scrap metal settlement, and
 * backfill Job Work / sale-order embed payments from tbl_old_jewelry_scrap_invoice_payments.
 * Also backfill product-list lines from OJB items when the list is empty (scrap metal wt visible on JWO).
 */

if (!function_exists('auragold_oj_scrap_payment_row_is_scrap_metal')) {
    function auragold_oj_scrap_payment_row_is_scrap_metal(array $row): bool
    {
        $pt = strtolower(trim((string) ($row['payment_type'] ?? '')));
        $dep = strtolower(trim((string) ($row['deposit_into'] ?? '')));
        if ($dep === 'scrap') {
            return true;
        }
        if ($pt !== '' && strpos($pt, 'scrap') !== false) {
            return true;
        }
        if ($pt !== '' && strpos($pt, 'metal') !== false && strpos($pt, 'exchange') !== false) {
            return true;
        }
        $pd = $row['payment_details'] ?? '';
        if (is_string($pd) && $pd !== '') {
            $j = json_decode($pd, true);
            if (is_array($j)) {
                if (isset($j['scrap_net_wt']) || isset($j['scrap_gross_wt']) || !empty($j['scrap_metal_id'])) {
                    return true;
                }
            }
        }

        return false;
    }
}

if (!function_exists('auragold_create_so_from_oj_scrap_row_is_scrap_metal')) {
    function auragold_create_so_from_oj_scrap_row_is_scrap_metal(array $row): bool
    {
        return auragold_oj_scrap_payment_row_is_scrap_metal($row);
    }
}

function auragold_jwo_resolve_ojb_invoice_id_from_sale_order($conn, array $edit_order): int
{
    $against = trim((string) ($edit_order['against_of'] ?? ''));
    $inv_no = '';
    if (preg_match('/^OJB-/i', $against)) {
        $inv_no = $against;
    } else {
        $c = (string) ($edit_order['comment'] ?? '');
        if (preg_match('/Job work \/ refinery from Old Jewellery scrap\s+(\S+)/i', $c, $m)) {
            $inv_no = trim($m[1], " \t\n\r\0\x0B—");
        }
    }
    if ($inv_no === '') {
        return 0;
    }
    $esc = mysqli_real_escape_string($conn, $inv_no);
    $oj = getRecord("SELECT id FROM tbl_old_jewelry_scrap_invoices WHERE invoice_no = '$esc' LIMIT 1");

    return ($oj && !empty($oj['id'])) ? (int) $oj['id'] : 0;
}

/** @return int[] */
function auragold_parse_oj_scrap_line_ids_from_so_comment(string $comment): array
{
    if (preg_match('/\blines:\s*([\d,\s]+)/i', $comment, $m)) {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', $m[1])))));
    }

    return [];
}

/**
 * Sum scrap modal weights from OJB payment_details JSON (scrap_gross_wt, scrap_net_wt, etc.).
 *
 * @return array{gross:float,net:float,less:float,stone:float,pure:float}|null
 */
function auragold_oj_scrap_sum_modal_weights_from_ojb_payments($conn, int $ojb_invoice_id): ?array
{
    $rows = getList(
        'SELECT payment_details, amount FROM tbl_old_jewelry_scrap_invoice_payments WHERE invoice_id = '
        . (int) $ojb_invoice_id . ' AND IFNULL(status,1) = 1'
    );
    if (!is_array($rows)) {
        return null;
    }
    $sum_gross = 0.0;
    $sum_net = 0.0;
    $sum_less = 0.0;
    $sum_stone = 0.0;
    $sum_pure = 0.0;
    $any = false;
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        if (!auragold_oj_scrap_payment_row_is_scrap_metal($r)) {
            continue;
        }
        if ((float) ($r['amount'] ?? 0) <= 0) {
            continue;
        }
        $pd = $r['payment_details'] ?? '';
        if (!is_string($pd) || trim($pd) === '') {
            continue;
        }
        $j = json_decode($pd, true);
        if (!is_array($j)) {
            continue;
        }
        $g = isset($j['scrap_gross_wt']) ? (float) $j['scrap_gross_wt'] : 0.0;
        $n = isset($j['scrap_net_wt']) ? (float) $j['scrap_net_wt'] : 0.0;
        $n_eff = $n > 0.00001 ? $n : ($g > 0.00001 ? $g : 0.0);
        if ($n_eff <= 0.00001) {
            continue;
        }
        $any = true;
        $sum_net += $n_eff;
        $sum_gross += $g > 0.00001 ? $g : $n_eff;
        $sum_less += isset($j['scrap_less_wt']) ? (float) $j['scrap_less_wt'] : 0.0;
        $sum_stone += isset($j['scrap_stone_wt']) ? (float) $j['scrap_stone_wt'] : 0.0;
        $sum_pure += isset($j['scrap_purity_wt']) ? (float) $j['scrap_purity_wt'] : 0.0;
    }
    if (!$any || $sum_net <= 0.00001) {
        return null;
    }
    $gross_out = $sum_gross > 0.00001 ? $sum_gross : $sum_net;

    return [
        'gross' => $gross_out,
        'net' => $sum_net,
        'less' => $sum_less,
        'stone' => $sum_stone,
        'pure' => $sum_pure,
    ];
}

/**
 * Apply summed scrap payment modal weights to one line (tbl_sale_order_items / embed shape).
 */
function auragold_oj_scrap_apply_modal_weights_to_sale_order_shape_line(array &$line, array $w): void
{
    $g = $w['gross'];
    $n = $w['net'];
    $less = $w['less'];
    $stone = $w['stone'];
    $pure = $w['pure'];

    $line['gross_weight'] = $g;
    $line['less_weight'] = $less;
    $line['net_weight'] = $n;
    $line['final_weight'] = $g > 0.00001 ? $g : $n;
    if ($pure > 0.00001) {
        $line['pure_weight'] = $pure;
        $line['purity_weight'] = $pure;
        $line['purity'] = $n > 0.00001 ? ($pure / $n) : (float) ($line['purity'] ?? 0);
    } else {
        $purity = (float) ($line['purity'] ?? 0);
        if ($purity > 0 && $n > 0.00001) {
            $pw = $purity > 1 ? $n * ($purity / 100) : $n * $purity;
            $line['purity_weight'] = $pw;
            $line['pure_weight'] = $pw;
        }
    }
    if (array_key_exists('stone_weight', $line)) {
        $line['stone_weight'] = $stone;
    }
    $rate = (float) ($line['rate'] ?? 0);
    if ($rate > 0 && $n > 0.00001) {
        $net_amt = $rate * $n;
        $line['amount'] = $net_amt;
        $line['net_amount'] = $net_amt;
        $tax = (float) ($line['tax_amount'] ?? 0);
        $line['net_amt_with_tax'] = $net_amt + $tax;
    }
}

/**
 * Apply modal weights to one OJB item row (gross_wt / net_wt keys) before mirroring to sale order.
 */
function auragold_oj_scrap_apply_modal_weights_to_ojb_item_shape_line(array &$line, array $w): void
{
    $g = $w['gross'];
    $n = $w['net'];
    $less = $w['less'];
    $stone = $w['stone'];
    $pure = $w['pure'];

    $line['gross_wt'] = $g;
    $line['less_wt'] = $less;
    $line['net_wt'] = $n;
    $line['final_wt'] = $g > 0.00001 ? $g : $n;
    if ($pure > 0.00001) {
        $line['pure_wt'] = $pure;
        $line['purity'] = $n > 0.00001 ? ($pure / $n) : (float) ($line['purity'] ?? 0);
    }
    $line['diamond_wt'] = 0;
    $line['gemstone_wt'] = $stone;
    $rate = (float) ($line['rate'] ?? 0);
    if ($rate > 0 && $n > 0.00001) {
        $amt = $rate * $n;
        $line['amount'] = $amt;
        $line['net_amt'] = $amt;
    }
}

/**
 * Build tbl_sale_order_items-shaped rows for EDIT_ORDER_DATA from OJB lines.
 *
 * @param int[] $line_ids_filter empty = all active lines on invoice
 * @return array<int, array<string, mixed>>
 */
function auragold_oj_scrap_build_embed_sale_order_items_from_ojb_lines($conn, int $ojb_invoice_id, int $sale_order_id, array $line_ids_filter): array
{
    $id_filter = '';
    if (!empty($line_ids_filter)) {
        $ids = implode(',', array_map('intval', $line_ids_filter));
        $id_filter = " AND id IN ($ids) ";
    }
    $items = getList(
        'SELECT * FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = ' . (int) $ojb_invoice_id
        . ' AND IFNULL(status,1) = 1' . $id_filter . ' ORDER BY id ASC'
    );
    if (!is_array($items) || empty($items)) {
        return [];
    }

    $ph = getRecord('SELECT id FROM tbl_products WHERE status = 1 ORDER BY id ASC LIMIT 1');
    $product_id = (int) ($ph['id'] ?? 0);
    if ($product_id < 1) {
        return [];
    }
    $ch = getRecord('SELECT id FROM tbl_product_characteristics WHERE product_id = ' . $product_id . ' AND status = 1 ORDER BY id ASC LIMIT 1');
    $characteristic_id = ($ch && !empty($ch['id'])) ? (int) $ch['id'] : null;

    $out = [];
    $tmp_id = 1;
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $desc = (string) ($it['description'] ?? 'Scrap refinery');
        $qty = (float) ($it['quantity'] ?? 1);
        $gross = (float) ($it['gross_wt'] ?? 0);
        $less = (float) ($it['less_wt'] ?? 0);
        $net = (float) ($it['net_wt'] ?? 0);
        $final = (float) ($it['final_wt'] ?? $net);
        $pure = (float) ($it['pure_wt'] ?? 0);
        $purity = (float) ($it['purity'] ?? 0);
        $purity_wt = ($net > 0 && $purity > 0) ? ($purity > 1 ? $net * ($purity / 100) : $net * $purity) : $pure;
        $rate = (float) ($it['rate'] ?? 0);
        $making = (float) ($it['making'] ?? 0);
        $tax = (float) ($it['tax'] ?? 0);
        $amount = (float) ($it['amount'] ?? 0);
        $net_amt = (float) ($it['net_amt'] ?? $amount);
        $net_amt_tax = $net_amt + $tax;
        $diamond_wt = (float) ($it['diamond_wt'] ?? 0);
        $gemstone_wt = (float) ($it['gemstone_wt'] ?? 0);
        $stone_wt = $diamond_wt + $gemstone_wt;

        $row = [
            'id' => $tmp_id++,
            'order_id' => $sale_order_id,
            'product_id' => $product_id,
            'product_characteristic_id' => $characteristic_id,
            'barcode' => $it['barcode'] ?? '',
            'product_name' => $desc !== '' ? $desc : 'Scrap refinery',
            'carat' => null,
            'quantity' => $qty,
            'gross_weight' => $gross,
            'less_weight' => $less,
            'purity' => $purity,
            'purity_weight' => $purity_wt,
            'final_weight' => $final,
            'net_weight' => $net,
            'pure_weight' => $pure,
            'rate' => $rate,
            'making_amount' => $making,
            'amount' => $amount,
            'tax_amount' => $tax,
            'net_amount' => $net_amt,
            'net_amt_with_tax' => $net_amt_tax,
            'design_no' => null,
            'status' => 1,
            'stone_weight' => $stone_wt,
        ];
        $out[] = $row;
    }

    return $out;
}

/**
 * Remove scrap-metal settlement rows from payment embed (OJB scrap payment should not appear on Job Work when from_oj_scrap=1).
 */
function auragold_jobwork_embed_strip_scrap_metal_payments(array &$edit_payments): void
{
    if (!is_array($edit_payments) || empty($edit_payments)) {
        return;
    }
    $edit_payments = array_values(array_filter($edit_payments, function ($ep) {
        if (!is_array($ep)) {
            return false;
        }

        return !auragold_oj_scrap_payment_row_is_scrap_metal($ep);
    }));
}

/**
 * When opening jobwork-order.php?from_oj_scrap=1:
 * - Optionally backfill tbl_sale_order_payments embed from OJB if missing (skipped when scrap payments are hidden on JWO)
 * - Backfill product list from OJB scrap lines when there are no lines (metal wt visible)
 *
 * @param int  $jobwork_order_id_existing tbl_jobwork_orders.id if any (used only to detect empty JWO lines path)
 * @param bool $hide_scrap_payment_rows   true when ?from_oj_scrap=1 — do not show scrap payment in payment table (metal stays on product lines)
 */
function auragold_jobwork_enrich_sale_order_embed_from_oj_scrap($conn, array &$edit_order, array &$edit_items, array &$edit_payments, int $jobwork_order_id_existing, bool $hide_scrap_payment_rows = false): void
{
    if (!is_array($edit_order) || empty($edit_order['id'])) {
        return;
    }

    $oid = auragold_jwo_resolve_ojb_invoice_id_from_sale_order($conn, $edit_order);
    if ($oid <= 0) {
        return;
    }

    $has_scrap_already = false;
    foreach ($edit_payments as $ep) {
        if (!is_array($ep)) {
            continue;
        }
        $pt = strtolower(trim((string) ($ep['payment_type'] ?? '')));
        $dep = strtolower(trim((string) ($ep['deposit_into'] ?? '')));
        if ($dep === 'scrap' || ($pt !== '' && strpos($pt, 'scrap') !== false)) {
            $has_scrap_already = true;
            break;
        }
    }

    if (!$hide_scrap_payment_rows && !$has_scrap_already) {
        $rows = getList("SELECT * FROM tbl_old_jewelry_scrap_invoice_payments WHERE invoice_id = $oid AND IFNULL(status,1) = 1 ORDER BY id ASC");
        if (!is_array($rows)) {
            $rows = [];
        }

        $scrap_rows = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            if ((float) ($r['amount'] ?? 0) <= 0) {
                continue;
            }
            if (!auragold_oj_scrap_payment_row_is_scrap_metal($r)) {
                continue;
            }
            $scrap_rows[] = $r;
        }

        if (!empty($scrap_rows)) {
            $soid = (int) $edit_order['id'];
            $out = [];
            $tmp_id = 1;
            foreach ($scrap_rows as $r) {
                $pt_disp = trim((string) ($r['payment_type'] ?? ''));
                if ($pt_disp === '') {
                    $pt_disp = 'Scrap';
                }
                $out[] = [
                    'id' => $tmp_id++,
                    'order_id' => $soid,
                    'payment_type' => $pt_disp,
                    'deposit_into' => $r['deposit_into'] ?? '',
                    'transaction_no' => $r['transaction_no'] ?? '',
                    'cheque_date' => $r['cheque_date'] ?? null,
                    'purity_carat' => $r['purity_carat'] ?? '',
                    'amount' => (float) ($r['amount'] ?? 0),
                    'previous_balance_amount' => 0,
                    'diamond_category' => $r['diamond_category'] ?? '',
                    'quantity' => (float) ($r['quantity'] ?? 0),
                    'payment_details' => $r['payment_details'] ?? '',
                    'status' => 1,
                ];
            }
            $edit_payments = $out;
        }
    }

    if (!is_array($edit_items)) {
        $edit_items = [];
    }
    if (count($edit_items) === 0) {
        $line_ids = auragold_parse_oj_scrap_line_ids_from_so_comment((string) ($edit_order['comment'] ?? ''));
        $built = auragold_oj_scrap_build_embed_sale_order_items_from_ojb_lines($conn, $oid, (int) $edit_order['id'], $line_ids);
        if (!empty($built)) {
            $edit_items = $built;
        }
    }

    // Single scrap line + scrap payment modal: show payment metal weights (e.g. 5 g) in product list, not only invoice line (e.g. 10 g).
    if ($oid > 0 && is_array($edit_items) && count($edit_items) === 1) {
        $w_modal = auragold_oj_scrap_sum_modal_weights_from_ojb_payments($conn, $oid);
        if ($w_modal !== null) {
            $it0 = &$edit_items[0];
            auragold_oj_scrap_apply_modal_weights_to_sale_order_shape_line($it0, $w_modal);
        }
    }

    if ($hide_scrap_payment_rows) {
        auragold_jobwork_embed_strip_scrap_metal_payments($edit_payments);
    }
}
