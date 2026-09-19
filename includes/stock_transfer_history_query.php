<?php
/**
 * Stock transfer list: same SELECT/joins as stock-history.php inward "Stock Availability (Wt)" query,
 * with WHERE scoped to one branch: non-outward lines with positive current or (SJ inward) opening qty/wt after journal clears current_*.
 *
 * @param mysqli      $conn
 * @param int         $branch_id
 * @param string|null $barcode_esc mysqli_real_escape_string() result, or null for full branch list
 * @param int         $session_default_branch_id Unused (kept for call-site compatibility).
 */
function auragold_stock_transfer_list_sql($conn, $branch_id, $barcode_esc = null, $session_default_branch_id = 0) {
    $bid = (int) $branch_id;

    $ref_col_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
    $stock_has_ref = ($ref_col_chk && mysqli_num_rows($ref_col_chk) >= 2);
    if ($ref_col_chk) {
        mysqli_free_result($ref_col_chk);
    }
    $sj_id_col_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_journal_id'");
    $stock_has_sjid = ($sj_id_col_chk && mysqli_num_rows($sj_id_col_chk) > 0);
    if ($sj_id_col_chk) {
        mysqli_free_result($sj_id_col_chk);
    }
    $sj_ref_join = '';
    if ($stock_has_ref) {
        $sj_ref_join = "
    LEFT JOIN tbl_stock_journal sj_ref ON (
        s.reference_type = 'stock_journal'
        AND s.reference_id = sj_ref.id
        AND sj_ref.status = 'active'
    )";
    }

    $sj_bc_opening_inner = "(
    sj_bc.id IS NOT NULL AND (
        ((sj_bc.item_id IS NULL OR sj_bc.item_id = 0) AND (sj_bc.invoice_id IS NULL OR sj_bc.invoice_id = 0))
        OR LOWER(IFNULL(sj_bc.voucher_type, '')) LIKE '%opening%'
        OR LOWER(TRIM(IFNULL(sj_bc.voucher_type, ''))) IN ('product_opening', 'opening')
    )
)";

    $inward_opening_sj_expr = $stock_has_ref
        ? "(
        (sj_ref.id IS NOT NULL AND (
            ((sj_ref.item_id IS NULL OR sj_ref.item_id = 0) AND (sj_ref.invoice_id IS NULL OR sj_ref.invoice_id = 0))
            OR LOWER(IFNULL(sj_ref.voucher_type, '')) LIKE '%opening%'
            OR LOWER(TRIM(IFNULL(sj_ref.voucher_type, ''))) IN ('product_opening', 'opening')
        ))
        OR (sj.id IS NOT NULL AND (
            ((sj.item_id IS NULL OR sj.item_id = 0) AND (sj.invoice_id IS NULL OR sj.invoice_id = 0))
            OR LOWER(IFNULL(sj.voucher_type, '')) LIKE '%opening%'
            OR LOWER(TRIM(IFNULL(sj.voucher_type, ''))) IN ('product_opening', 'opening')
        ))
        OR $sj_bc_opening_inner
    )"
        : "(
        (sj.id IS NOT NULL AND (
            ((sj.item_id IS NULL OR sj.item_id = 0) AND (sj.invoice_id IS NULL OR sj.invoice_id = 0))
            OR LOWER(IFNULL(sj.voucher_type, '')) LIKE '%opening%'
            OR LOWER(TRIM(IFNULL(sj.voucher_type, ''))) IN ('product_opening', 'opening')
        ))
        OR $sj_bc_opening_inner
    )";

    $inward_any_sj_expr = $stock_has_ref
        ? '(sj.id IS NOT NULL OR sj_ref.id IS NOT NULL OR sj_bc.id IS NOT NULL)'
        : '(sj.id IS NOT NULL OR sj_bc.id IS NOT NULL)';

    // save-stock-journal.php zeros current_* on per-line inward purchase rows after posting consolidated outward;
    // opening_* still holds piece weight — same fallback as stock-transfer-save.php move_wt/move_qty.
    $sj_link_parts = [];
    if ($stock_has_ref) {
        $sj_link_parts[] = "IFNULL(s.reference_type,'') = 'stock_journal'";
    }
    if ($stock_has_sjid) {
        $sj_link_parts[] = 'IFNULL(s.stock_journal_id,0) > 0';
    }
    $sj_opening_onhand = !empty($sj_link_parts)
        ? '(s.stock_type = \'purchase\' AND (COALESCE(s.opening_weight,0) > 0 OR COALESCE(s.opening_qty,0) > 0) AND (' . implode(' OR ', $sj_link_parts) . '))'
        : '0';

    // List transferable at this branch; exclude outward rows (save rejects those).
    $stock_transfer_where = "s.status = 1 AND IFNULL(s.stock_type,'') NOT IN ('outward')";
    $stock_transfer_where .= ' AND (COALESCE(s.current_weight,0) > 0 OR COALESCE(s.current_qty,0) > 0 OR (' . $sj_opening_onhand . '))';
    // Strict branch: only stock assigned to the selected source branch (matches Stock History Inward branch filter).
    $stock_transfer_where .= " AND s.branch_id = " . $bid;
    if ($barcode_esc !== null && $barcode_esc !== '') {
        $stock_transfer_where .= " AND BINARY IFNULL(s.barcode,'') = BINARY '" . $barcode_esc . "'";
    }

    $image_urls_select = 'NULL AS image_urls';
    $pimg = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_products LIKE 'images'");
    if ($pimg && mysqli_num_rows($pimg) > 0) {
        $image_urls_select = "NULLIF(TRIM(COALESCE(p.images,'')), '') AS image_urls";
    }
    if ($pimg) {
        mysqli_free_result($pimg);
    }

    $sql = "
    SELECT 
        s.*,
        p.name as product_name,
        p.article as article,
        m.display_name as metal_name,
        b.name as branch_name,
        pc.hsn,
        pc.sku_code,
        pc.sku_code as design_no,
        s.value as net_amt,
        DATE(s.created_at) as transaction_date,
        COALESCE(NULLIF(pi_item.metal_qty, 0), NULLIF(pi_item_sj.metal_qty, 0), NULLIF(pi_item.quantity, 0), NULLIF(pi_item_sj.quantity, 0), s.opening_qty, s.current_qty) as qty,
        COALESCE(NULLIF(pi_item.metal_weight, 0), NULLIF(pi_item_sj.metal_weight, 0), NULLIF(pi_item.gross_weight, 0), NULLIF(pi_item_sj.gross_weight, 0), s.opening_weight, s.current_weight) as gross_wt,
        s.opening_purity as purity,
        (COALESCE(NULLIF(pi_item.metal_weight, 0), NULLIF(pi_item_sj.metal_weight, 0), NULLIF(pi_item.gross_weight, 0), NULLIF(pi_item_sj.gross_weight, 0), s.opening_weight, s.current_weight) * (CASE WHEN s.opening_purity <= 1 THEN s.opening_purity ELSE s.opening_purity / 100 END)) as pure_wt,
        s.final_weight,
        s.rate,
        s.value as amount,
        COALESCE(
            s.barcode,
            sj.barcode,
            sj_bc.barcode,
            pi_item.barcode,
            (SELECT sj2.barcode 
             FROM tbl_stock_journal sj2 
             WHERE sj2.product_id = s.product_id 
             AND (sj2.product_characteristic_id = s.product_characteristic_id OR (sj2.product_characteristic_id IS NULL AND s.product_characteristic_id IS NULL))
             AND sj2.status = 'active'
             AND sj2.barcode IS NOT NULL 
             AND sj2.barcode != ''
             AND DATE(sj2.sj_date) = DATE(s.created_at)
             AND ABS(sj2.gross_weight - s.current_weight) < 0.001
             AND ABS(sj2.net_amt_with_tax - s.value) < 0.01
             AND (pi_item.id IS NULL OR sj2.item_id = pi_item.id)
             ORDER BY sj2.id ASC
             LIMIT 1),
            pc.barcode,
            (SELECT pc2.barcode FROM tbl_product_characteristics pc2 
             WHERE pc2.product_id = s.product_id AND pc2.metal_id = s.metal_id 
             AND pc2.status = 1 AND pc2.barcode IS NOT NULL AND pc2.barcode != '' 
             ORDER BY (pc2.branch_id = COALESCE(s.branch_id, 1)) DESC, pc2.id ASC LIMIT 1),
            ''
        ) as barcode,
        COALESCE(pi.id, 0) as invoice_id,
        '' as rfid,
        '' as location,
        COALESCE(
            sj.sj_invoice_no,
            sj_bc.sj_invoice_no,
            pi.invoice_no, 
            sr.return_no,
            ''
        ) as against_invoice_no,
        CASE 
            WHEN $inward_any_sj_expr THEN 'Stock Journal'
            WHEN $inward_opening_sj_expr THEN 'opening'
            WHEN s.stock_type = 'sale_return' THEN 'Sale Return'
            ELSE s.stock_type
        END as type_of_voucher,
        CASE 
            WHEN $inward_any_sj_expr THEN 'Stock Journal'
            WHEN $inward_opening_sj_expr THEN 'opening'
            WHEN s.stock_type = 'sale_return' THEN 'Sale Return'
            ELSE s.stock_type
        END as voucher_type,
        '' as requested_qty,
        '' as requested_wt,
        0.00 as stone_wt,
        0.00 as diamond_wt,
        0.00 as less_wt,
        0.00 as purity_wt,
        0.00 as wastage_per,
        0.00 as wastage_wt,
        COALESCE(NULLIF(pi_item.metal_weight, 0), NULLIF(pi_item_sj.metal_weight, 0), NULLIF(pi_item.gross_weight, 0), NULLIF(pi_item_sj.gross_weight, 0), s.opening_weight, s.current_weight) as net_wt,
        0.00 as alloy_wt,
        s.final_weight as final_wt,
        0.00 as standard_wt,
        0.00 as actual_wt,
        0.00 as national_wt,
        '' as name,
        0.00 as making_rate,
        0.00 as making_amt,
        '' as hui_code,
        0.00 as packet_wt,
        0.00 as packet_length,
        '' as hallmark1,
        '' as hallmark2,
        0.00 as net_amt_with_tax,
        0.00 as tax_amt,
        0.00 as discount_per,
        0.00 as discount_amt,
        0.00 as metal_value,
        $image_urls_select
    FROM tbl_stock s
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    LEFT JOIN tbl_stock_journal sj ON (
        sj.product_id = s.product_id 
        AND (sj.product_characteristic_id = s.product_characteristic_id OR (sj.product_characteristic_id IS NULL AND s.product_characteristic_id IS NULL))
        AND DATE(sj.sj_date) = DATE(s.created_at)
        AND ABS(TIMESTAMPDIFF(SECOND, sj.created_at, s.created_at)) <= 300
        AND sj.status = 'active'
        AND s.stock_type = 'purchase'
        AND ABS(sj.net_amt_with_tax - s.value) < 0.01
        AND ABS(sj.gross_weight - s.current_weight) < 0.001
    )
    $sj_ref_join
    LEFT JOIN (
        SELECT sj_x.*
        FROM tbl_stock_journal sj_x
        INNER JOIN (
            SELECT product_id, barcode, MAX(id) AS sj_max_id
            FROM tbl_stock_journal
            WHERE status = 'active'
            AND barcode IS NOT NULL
            AND barcode != ''
            GROUP BY product_id, barcode
        ) sj_pick ON sj_pick.sj_max_id = sj_x.id
    ) sj_bc ON sj_bc.product_id = s.product_id
        AND BINARY IFNULL(sj_bc.barcode,'') = BINARY IFNULL(s.barcode,'')
        AND s.stock_type = 'purchase'
        AND s.barcode IS NOT NULL
        AND s.barcode != ''
    LEFT JOIN tbl_purchase_invoice_items pi_item ON (
        pi_item.product_id = s.product_id 
        AND (pi_item.product_characteristic_id = s.product_characteristic_id OR (pi_item.product_characteristic_id IS NULL AND s.product_characteristic_id IS NULL))
        AND DATE(pi_item.created_at) = DATE(s.created_at)
        AND ABS(TIMESTAMPDIFF(SECOND, pi_item.created_at, s.created_at)) <= 5
        AND pi_item.status = 1
        AND s.stock_type = 'purchase'
        AND sj.id IS NULL
        AND (
            sj_bc.id IS NULL
            OR (sj_bc.item_id IS NOT NULL AND sj_bc.item_id <> 0)
        )
    )
    LEFT JOIN tbl_purchase_invoices pi ON pi_item.invoice_id = pi.id
    LEFT JOIN tbl_purchase_invoice_items pi_item_sj ON (sj.item_id = pi_item_sj.id AND sj.item_id IS NOT NULL)
    LEFT JOIN tbl_sale_return_items sr_item ON (
        sr_item.product_id = s.product_id 
        AND (sr_item.product_characteristic_id = s.product_characteristic_id OR (sr_item.product_characteristic_id IS NULL AND s.product_characteristic_id IS NULL))
        AND DATE(sr_item.created_at) = DATE(s.created_at)
        AND ABS(TIMESTAMPDIFF(SECOND, sr_item.created_at, s.created_at)) <= 5
        AND sr_item.status = 1
        AND s.stock_type = 'sale_return'
    )
    LEFT JOIN tbl_sale_returns sr ON sr_item.return_id = sr.id
    WHERE $stock_transfer_where
    ORDER BY s.created_at DESC
";

    if ($barcode_esc !== null && $barcode_esc !== '') {
        $sql .= ' LIMIT 1';
    }

    return $sql;
}
