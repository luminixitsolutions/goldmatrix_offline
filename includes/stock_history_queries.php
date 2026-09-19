<?php
// Link inward rows to stock journal via reference columns when present (reliable for product opening voucher)
$stock_has_ref = false;
$ref_col_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
if ($ref_col_chk && mysqli_num_rows($ref_col_chk) >= 2) {
    $stock_has_ref = true;
}
if ($ref_col_chk) {
    mysqli_free_result($ref_col_chk);
}

require_once __DIR__ . '/auragold_metal_exchange_stock.php';
$auragold_me_ref_sql_in = auragold_metal_exchange_reference_types_sql_list_safe(isset($conn) && $conn instanceof mysqli ? $conn : null);

$sj_ref_join = '';
$so_me_join = '';
if ($stock_has_ref) {
    $sj_ref_join = "
    LEFT JOIN tbl_stock_journal sj_ref ON (
        s.reference_type = 'stock_journal'
        AND s.reference_id = sj_ref.id
        AND sj_ref.status = 'active'
    )";
    $so_me_join = "
    LEFT JOIN tbl_sale_orders so_me_inv ON (
        s.reference_type = 'sale_order_metal_exchange'
        AND s.reference_id = so_me_inv.id
    )
    LEFT JOIN tbl_sale_invoices si_me_inv ON (
        s.reference_type = 'sale_invoice_metal_exchange'
        AND s.reference_id = si_me_inv.id
    )
    LEFT JOIN tbl_sale_quotations sq_me_inv ON (
        s.reference_type = 'sale_quotation_metal_exchange'
        AND s.reference_id = sq_me_inv.id
    )
    LEFT JOIN tbl_sale_returns sr_me_inv ON (
        s.reference_type = 'sale_return_metal_exchange'
        AND s.reference_id = sr_me_inv.id
    )
    LEFT JOIN tbl_purchase_invoices pi_me_inv ON (
        s.reference_type = 'purchase_invoice_metal_exchange'
        AND s.reference_id = pi_me_inv.id
    )
    LEFT JOIN tbl_purchase_quotations pq_me_inv ON (
        s.reference_type = 'purchase_quotation_metal_exchange'
        AND s.reference_id = pq_me_inv.id
    )
    LEFT JOIN tbl_purchase_returns pr_me_inv ON (
        s.reference_type = 'purchase_return_metal_exchange'
        AND s.reference_id = pr_me_inv.id
    )
    LEFT JOIN tbl_payment_vouchers pv_me_inv ON (
        s.reference_type = 'payment_voucher_metal_exchange'
        AND s.reference_id = pv_me_inv.id
    )
    LEFT JOIN tbl_receipt_vouchers rv_me_inv ON (
        s.reference_type = 'receipt_voucher_metal_exchange'
        AND s.reference_id = rv_me_inv.id
    )
    LEFT JOIN tbl_advance_payments ap_me_inv ON (
        s.reference_type = 'advance_payment_metal_exchange'
        AND s.reference_id = ap_me_inv.id
    )
    LEFT JOIN tbl_old_jewelry_scrap_invoices ojsi_me_inv ON (
        s.reference_type = 'old_jewelry_scrap_invoice_metal_exchange'
        AND s.reference_id = ojsi_me_inv.id
    )
    LEFT JOIN tbl_material_issues mi_me_inv ON (
        s.reference_type = 'material_issue_metal_exchange'
        AND s.reference_id = mi_me_inv.id
    )
    LEFT JOIN tbl_material_receives mr_me_inv ON (
        s.reference_type = 'material_receive_metal_exchange'
        AND s.reference_id = mr_me_inv.id
    )
    LEFT JOIN tbl_jobwork_orders jwo_me_inv ON (
        s.reference_type = 'jobwork_order_metal_exchange'
        AND s.reference_id = jwo_me_inv.id
    )
    LEFT JOIN tbl_jobwork_invoices jwi_me_inv ON (
        s.reference_type = 'jobwork_invoice_metal_exchange'
        AND s.reference_id = jwi_me_inv.id
    )
    LEFT JOIN tbl_old_jewelry_scrap_invoices ojstk_me_inv ON (
        s.reference_type = 'old_jewellery_scrap_stock_in_metal_exchange'
        AND s.reference_id = ojstk_me_inv.id
    )
    LEFT JOIN tbl_consignment_in cin_me_inv ON (
        s.reference_type = 'consignment_in_metal_exchange'
        AND s.reference_id = cin_me_inv.id
    )
    LEFT JOIN tbl_consignment_out cout_me_inv ON (
        s.reference_type = 'consignment_out_metal_exchange'
        AND s.reference_id = cout_me_inv.id
    )
    LEFT JOIN tbl_pos_sale_invoices posi_me_inv ON (
        s.reference_type = 'pos_sale_invoice_metal_exchange'
        AND s.reference_id = posi_me_inv.id
    )
    LEFT JOIN tbl_payment_vouchers pv_rv_inv ON (
        s.reference_type = 'payment_voucher'
        AND s.reference_id = pv_rv_inv.id
    )
    LEFT JOIN tbl_receipt_vouchers rv_rv_inv ON (
        s.reference_type = 'receipt_voucher'
        AND s.reference_id = rv_rv_inv.id
    )";
}
// Match journal by barcode when strict join (time/amount/weight) fails - e.g. stock.value = net_amount but SJ has net_amt_with_tax
$sj_bc_opening_inner = "(
    sj_bc.id IS NOT NULL AND (
        ((sj_bc.item_id IS NULL OR sj_bc.item_id = 0) AND (sj_bc.invoice_id IS NULL OR sj_bc.invoice_id = 0))
        OR LOWER(IFNULL(sj_bc.voucher_type, '')) LIKE '%opening%'
        OR LOWER(TRIM(IFNULL(sj_bc.voucher_type, ''))) IN ('product_opening', 'opening')
    )
)";

// Product opening: SJ row has no PI (item_id/invoice_id null) or voucher_type marks opening
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

// Purchase lines created via stock journal but tied to a purchase invoice line — show "Purchase Invoice", not "Stock Journal"
$inward_purchase_invoice_voucher_expr = "(s.stock_type = 'purchase' AND (pi_item.id IS NOT NULL OR pi_item_sj.id IS NOT NULL))";

// Document metal exchange rows: tagged on tbl_stock.reference_type (avoid mis-label as PI/SJ)
$doc_metal_exchange_voucher_expr = $stock_has_ref
    ? "(s.reference_type IN ($auragold_me_ref_sql_in))"
    : '(1 = 0)';
$against_invoice_me_docs_coalesce = $stock_has_ref ? "
            NULLIF(TRIM(so_me_inv.order_no), ''),
            NULLIF(TRIM(si_me_inv.invoice_no), ''),
            NULLIF(TRIM(sq_me_inv.quotation_no), ''),
            NULLIF(TRIM(sr_me_inv.return_no), ''),
            NULLIF(TRIM(pi_me_inv.invoice_no), ''),
            NULLIF(TRIM(pq_me_inv.quotation_no), ''),
            NULLIF(TRIM(pr_me_inv.return_no), ''),
            NULLIF(TRIM(pv_me_inv.voucher_no), ''),
            NULLIF(TRIM(rv_me_inv.voucher_no), ''),
            NULLIF(TRIM(ap_me_inv.voucher_no), ''),
            NULLIF(TRIM(ojsi_me_inv.invoice_no), ''),
            NULLIF(TRIM(mi_me_inv.material_issue_no), ''),
            NULLIF(TRIM(mr_me_inv.material_receive_no), ''),
            NULLIF(TRIM(jwo_me_inv.jobwork_no), ''),
            NULLIF(TRIM(jwi_me_inv.invoice_no), ''),
            NULLIF(TRIM(ojstk_me_inv.invoice_no), ''),
            NULLIF(TRIM(cin_me_inv.consignment_no), ''),
            NULLIF(TRIM(cout_me_inv.consignment_no), ''),
            NULLIF(TRIM(posi_me_inv.invoice_no), ''),
            NULLIF(TRIM(pv_rv_inv.voucher_no), ''),
            NULLIF(TRIM(rv_rv_inv.voucher_no), ''),
            " : '';

$doc_me_voucher_label_case = "
            CASE s.reference_type
                WHEN 'sale_order_metal_exchange' THEN 'Sale Order — Metal Exchange'
                WHEN 'sale_invoice_metal_exchange' THEN 'Sale Invoice — Metal Exchange'
                WHEN 'sale_quotation_metal_exchange' THEN 'Sale Quotation — Metal Exchange'
                WHEN 'sale_return_metal_exchange' THEN 'Sale Return — Metal Exchange'
                WHEN 'purchase_invoice_metal_exchange' THEN 'Purchase Invoice — Metal Exchange'
                WHEN 'purchase_quotation_metal_exchange' THEN 'Purchase Quotation — Metal Exchange'
                WHEN 'purchase_return_metal_exchange' THEN 'Purchase Return — Metal Exchange'
                WHEN 'payment_voucher_metal_exchange' THEN 'Payment Voucher — Metal Exchange'
                WHEN 'receipt_voucher_metal_exchange' THEN 'Receipt Voucher — Metal Exchange'
                WHEN 'advance_payment_metal_exchange' THEN 'Advance Payment — Metal Exchange'
                WHEN 'old_jewelry_scrap_invoice_metal_exchange' THEN 'Old Jewelry Scrap Invoice — Metal Exchange'
                WHEN 'material_issue_metal_exchange' THEN 'Material Issue — Metal Exchange'
                WHEN 'material_receive_metal_exchange' THEN 'Material Receive — Metal Exchange'
                WHEN 'jobwork_order_metal_exchange' THEN 'Jobwork Order — Metal Exchange'
                WHEN 'jobwork_invoice_metal_exchange' THEN 'Jobwork Invoice — Metal Exchange'
                WHEN 'old_jewellery_scrap_stock_in_metal_exchange' THEN 'Old Jewellery Scrap Stock In — Metal Exchange'
                WHEN 'consignment_in_metal_exchange' THEN 'Consignment In — Metal Exchange'
                WHEN 'consignment_out_metal_exchange' THEN 'Consignment Out — Metal Exchange'
                WHEN 'pos_sale_invoice_metal_exchange' THEN 'POS Sale Invoice — Metal Exchange'
                ELSE 'Metal Exchange'
            END
            ";

$sj_attach_item_select = $stock_has_ref
    ? 'COALESCE(sj.item_id, sj_ref.item_id, sj_bc.item_id, pi_item.id, pi_item_sj.id, 0) as sj_attach_item_id'
    : 'COALESCE(sj.item_id, sj_bc.item_id, pi_item.id, pi_item_sj.id, 0) as sj_attach_item_id';

$inward_query = "
    SELECT 
        s.*,
        p.name as product_name,
        p.article as article,
        m.display_name as metal_name,
        b.name as branch_name,
        pc.hsn,
        pc.sku_code,
        COALESCE(pc.carat, 0) as characteristic_carat,
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
            CASE WHEN s.stock_type = 'opening' AND pc.id IS NOT NULL AND NULLIF(TRIM(pc.barcode), '') IS NOT NULL THEN pc.barcode END,
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
        {$sj_attach_item_select},
        '' as rfid,
        '' as location,
        COALESCE(
            sj.sj_invoice_no,
            sj_bc.sj_invoice_no,
            pi.invoice_no, 
            sr.return_no,
            $against_invoice_me_docs_coalesce
            NULLIF(TRIM(jwo_di_inv.jobwork_no), ''),
            NULLIF(TRIM(jwo_di_inv.jobwork_queue_no), ''),
            ''
        ) as against_invoice_no,
        COALESCE(NULLIF(TRIM(pi.invoice_no), ''), NULLIF(TRIM(sr.return_no), ''), '') as invoice_no,
        /* Purchase invoice first. Document metal exchange before generic SJ match. */
        CASE 
            WHEN $doc_metal_exchange_voucher_expr THEN $doc_me_voucher_label_case
            WHEN COALESCE(NULLIF(TRIM(s.reference_type), ''), '') = 'receipt_voucher' THEN 'Receipt Voucher'
            WHEN COALESCE(NULLIF(TRIM(s.reference_type), ''), '') = 'payment_voucher' THEN 'Payment Voucher'
            WHEN s.stock_type = 'balance' AND COALESCE(NULLIF(TRIM(s.reference_type), ''), '') = 'jobwork_diamond_transfer' THEN 'Jobwork Transfer'
            WHEN s.stock_type = 'balance' THEN 'Balance'
            WHEN s.stock_type = 'sale_return' THEN 'Sale Return'
            WHEN $inward_purchase_invoice_voucher_expr THEN 'Purchase Invoice'
            WHEN $inward_any_sj_expr THEN 'Stock Journal'
            WHEN $inward_opening_sj_expr THEN 'opening'
            ELSE s.stock_type
        END as type_of_voucher,
        CASE 
            WHEN $doc_metal_exchange_voucher_expr THEN $doc_me_voucher_label_case
            WHEN COALESCE(NULLIF(TRIM(s.reference_type), ''), '') = 'receipt_voucher' THEN 'Receipt Voucher'
            WHEN COALESCE(NULLIF(TRIM(s.reference_type), ''), '') = 'payment_voucher' THEN 'Payment Voucher'
            WHEN s.stock_type = 'balance' AND COALESCE(NULLIF(TRIM(s.reference_type), ''), '') = 'jobwork_diamond_transfer' THEN 'Jobwork Transfer'
            WHEN s.stock_type = 'balance' THEN 'Balance'
            WHEN s.stock_type = 'sale_return' THEN 'Sale Return'
            WHEN $inward_purchase_invoice_voucher_expr THEN 'Purchase Invoice'
            WHEN $inward_any_sj_expr THEN 'Stock Journal'
            WHEN $inward_opening_sj_expr THEN 'opening'
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
        0.00 as metal_value
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
        AND (s.reference_type IS NULL OR s.reference_type NOT IN ($auragold_me_ref_sql_in))
    )
    $sj_ref_join
    $so_me_join
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
        AND sj_bc.barcode COLLATE utf8mb4_unicode_ci = s.barcode COLLATE utf8mb4_unicode_ci
        AND s.stock_type = 'purchase'
        AND (s.reference_type IS NULL OR s.reference_type NOT IN ($auragold_me_ref_sql_in))
        AND s.barcode IS NOT NULL
        AND s.barcode != ''
    LEFT JOIN tbl_purchase_invoice_items pi_item ON (
        pi_item.product_id = s.product_id 
        AND (pi_item.product_characteristic_id = s.product_characteristic_id OR (pi_item.product_characteristic_id IS NULL AND s.product_characteristic_id IS NULL))
        AND DATE(pi_item.created_at) = DATE(s.created_at)
        AND ABS(TIMESTAMPDIFF(SECOND, pi_item.created_at, s.created_at)) <= 5
        AND pi_item.status = 1
        AND s.stock_type = 'purchase'
        AND (s.reference_type IS NULL OR s.reference_type NOT IN ($auragold_me_ref_sql_in))
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
    LEFT JOIN tbl_jobwork_orders jwo_di_inv ON (
        s.stock_type = 'balance'
        AND s.reference_type = 'jobwork_diamond_transfer'
        AND jwo_di_inv.id = s.reference_id
    )
    WHERE $inward_where
    ORDER BY s.created_at DESC
";

$inward_stock_all = getList($inward_query);
if (!is_array($inward_stock_all)) {
    $inward_stock_all = [];
}

$oj_inward_all = [];
$oj_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
if ($oj_tbl && mysqli_num_rows($oj_tbl) > 0) {
    mysqli_free_result($oj_tbl);
    $oj_where = "ojs.comment LIKE '%auragold_oj_refine|%' AND ji.id IS NOT NULL";
    if ($product_id > 0) {
        $oj_where .= ' AND ji.product_id = ' . (int) $product_id;
    }
    if ($characteristic_id > 0) {
        $oj_where .= ' AND ji.product_characteristic_id = ' . (int) $characteristic_id;
    }
    if ($adv_branch > 0) {
        $oj_where .= ' AND ojs.branch_id = ' . (int) $adv_branch;
    }
    if ($adv_category > 0) {
        $oj_where .= ' AND p.id IS NOT NULL AND p.category_id = ' . (int) $adv_category;
    }
    if ($adv_barcode !== '') {
        $bc_like = '%' . esc(str_replace(['%', '_'], ['\\%', '\\_'], $adv_barcode)) . '%';
        $oj_where .= " AND ojs.barcode IS NOT NULL AND TRIM(ojs.barcode) != '' AND ojs.barcode LIKE '$bc_like'";
    }
    $oj_sql = "
    SELECT
        0 AS id,
        ji.product_id,
        ji.product_characteristic_id,
        ojs.branch_id,
        pc.metal_id AS metal_id,
        1 AS status,
        p.name AS product_name,
        p.article AS article,
        m.display_name AS metal_name,
        b.name AS branch_name,
        pc.hsn,
        pc.sku_code,
        COALESCE(pc.carat, 0) AS characteristic_carat,
        ojs.amount AS net_amt,
        DATE(ojs.created_at) AS transaction_date,
        ojs.created_at AS created_at,
        COALESCE(ojs.quantity, 1) AS qty,
        ojs.gross_wt AS gross_wt,
        ojs.purity AS purity,
        (ojs.gross_wt * (CASE WHEN ojs.purity <= 1 THEN ojs.purity ELSE ojs.purity / 100 END)) AS pure_wt,
        ojs.final_wt AS final_weight,
        ojs.rate AS rate,
        ojs.amount AS amount,
        ojs.amount AS amt,
        IFNULL(ojs.barcode, '') AS barcode,
        0 AS invoice_id,
        0 AS sj_attach_item_id,
        '' AS rfid,
        IFNULL(ojs.location, '') AS location,
        TRIM(IFNULL(ojs.against_invoice_no, '')) AS against_invoice_no,
        TRIM(IFNULL(ojs.invoice_no, '')) AS invoice_no,
        IFNULL(NULLIF(TRIM(ojs.voucher_type), ''), 'Refinery - Jobwork') AS type_of_voucher,
        IFNULL(NULLIF(TRIM(ojs.voucher_type), ''), 'Refinery - Jobwork') AS voucher_type,
        '' AS requested_qty,
        '' AS requested_wt,
        0.00 AS stone_wt,
        0.00 AS diamond_wt,
        IFNULL(ojs.less_wt, 0) AS less_wt,
        0.00 AS purity_wt,
        0.00 AS wastage_per,
        0.00 AS wastage_wt,
        IFNULL(ojs.net_wt, ojs.gross_wt) AS net_wt,
        0.00 AS alloy_wt,
        IFNULL(ojs.final_wt, 0) AS final_wt,
        0.00 AS standard_wt,
        0.00 AS actual_wt,
        0.00 AS national_wt,
        '' AS name,
        0.00 AS making_rate,
        0.00 AS making_amt,
        '' AS hui_code,
        0.00 AS packet_wt,
        0.00 AS packet_length,
        '' AS hallmark1,
        '' AS hallmark2,
        0.00 AS net_amt_with_tax,
        0.00 AS tax_amt,
        0.00 AS discount_per,
        0.00 AS discount_amt,
        0.00 AS metal_value,
        0.00 AS purchase,
        ojs.id AS old_jewelry_stock_id
    FROM tbl_old_jewelry_stock ojs
    INNER JOIN tbl_jobwork_order_items ji ON ji.id = ojs.source_item_id
    LEFT JOIN tbl_products p ON ji.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON ji.product_characteristic_id = pc.id AND pc.status = 1
    LEFT JOIN tbl_metal m ON pc.metal_id = m.id
    LEFT JOIN tbl_branches b ON ojs.branch_id = b.id
    WHERE $oj_where
    ORDER BY ojs.created_at DESC
    ";
    $oj_inward_all = getList($oj_sql);
    if (!is_array($oj_inward_all)) {
        $oj_inward_all = [];
    }
}

$inward_totals_all = array_merge($inward_stock_all, $oj_inward_all);
usort($inward_totals_all, function ($a, $b) {
    $ta = strtotime((string) ($a['transaction_date'] ?? '')) ?: 0;
    $tb = strtotime((string) ($b['transaction_date'] ?? '')) ?: 0;
    if ($ta !== $tb) {
        return $tb <=> $ta;
    }
    $ca = strtotime((string) ($a['created_at'] ?? '')) ?: 0;
    $cb = strtotime((string) ($b['created_at'] ?? '')) ?: 0;
    if ($ca !== $cb) {
        return $cb <=> $ca;
    }
    $ia = (int) ($a['id'] ?? 0);
    $ib = (int) ($b['id'] ?? 0);
    if ($ia !== $ib) {
        return $ib <=> $ia;
    }
    $oa = (int) ($a['old_jewelry_stock_id'] ?? 0);
    $ob = (int) ($b['old_jewelry_stock_id'] ?? 0);

    return $ob <=> $oa;
});

$inward_total = count($inward_totals_all);
$inward_total_pages = $inward_total > 0 ? (int) ceil($inward_total / $inward_per_page) : 1;
$inward_offset_safe = max(0, (int) $inward_offset);
$inward_data = array_slice($inward_totals_all, $inward_offset_safe, (int) $inward_per_page);

// Calculate totals from all inward data
$inward_totals = [
    'total_net_amt' => 0,
    'total_qty' => 0,
    'total_gross_wt' => 0,
    'total_pure_wt' => 0,
    'total_requested_qty' => 0,
    'total_requested_wt' => 0,
    'total_stone_wt' => 0,
    'total_diamond_wt' => 0,
    'total_less_wt' => 0,
    'total_purity_wt' => 0,
    'total_wastage_wt' => 0,
    'total_net_wt' => 0,
    'total_alloy_wt' => 0,
    'total_final_wt' => 0,
    'total_standard_wt' => 0,
    'total_actual_wt' => 0,
    'total_national_wt' => 0,
    'total_making_amt' => 0,
    'total_amount' => 0,
    'total_packet_wt' => 0,
    'total_packet_length' => 0,
    'total_net_amt_with_tax' => 0,
    'total_tax_amt' => 0,
    'total_discount_amt' => 0,
    'total_metal_value' => 0,
    'total_purchase' => 0
];
foreach ($inward_totals_all as $tot_row) {
    $inward_totals['total_net_amt'] += (float)($tot_row['net_amt'] ?? 0);
    $inward_totals['total_qty'] += (float)($tot_row['qty'] ?? 0);
    $inward_totals['total_gross_wt'] += (float)($tot_row['gross_wt'] ?? 0);
    $inward_totals['total_pure_wt'] += (float)($tot_row['pure_wt'] ?? 0);
    $inward_totals['total_requested_qty'] += (float)($tot_row['requested_qty'] ?? 0);
    $inward_totals['total_requested_wt'] += (float)($tot_row['requested_wt'] ?? 0);
    $inward_totals['total_stone_wt'] += (float)($tot_row['stone_wt'] ?? 0);
    $inward_totals['total_diamond_wt'] += (float)($tot_row['diamond_wt'] ?? 0);
    $inward_totals['total_less_wt'] += (float)($tot_row['less_wt'] ?? 0);
    $inward_totals['total_purity_wt'] += (float)($tot_row['purity_wt'] ?? 0);
    $inward_totals['total_wastage_wt'] += (float)($tot_row['wastage_wt'] ?? 0);
    $inward_totals['total_net_wt'] += (float)($tot_row['net_wt'] ?? 0);
    $inward_totals['total_alloy_wt'] += (float)($tot_row['alloy_wt'] ?? 0);
    $inward_totals['total_final_wt'] += (float)($tot_row['final_wt'] ?? 0);
    $inward_totals['total_standard_wt'] += (float)($tot_row['standard_wt'] ?? 0);
    $inward_totals['total_actual_wt'] += (float)($tot_row['actual_wt'] ?? 0);
    $inward_totals['total_national_wt'] += (float)($tot_row['national_wt'] ?? 0);
    $inward_totals['total_making_amt'] += (float)($tot_row['making_amt'] ?? 0);
    $inward_totals['total_amount'] += (float)($tot_row['amount'] ?? 0);
    $inward_totals['total_packet_wt'] += (float)($tot_row['packet_wt'] ?? 0);
    $inward_totals['total_packet_length'] += (float)($tot_row['packet_length'] ?? 0);
    $inward_totals['total_net_amt_with_tax'] += (float)($tot_row['net_amt_with_tax'] ?? 0);
    $inward_totals['total_tax_amt'] += (float)($tot_row['tax_amt'] ?? 0);
    $inward_totals['total_discount_amt'] += (float)($tot_row['discount_amt'] ?? 0);
    $inward_totals['total_metal_value'] += (float)($tot_row['metal_value'] ?? 0);
    $inward_totals['total_purchase'] += (float)($tot_row['purchase'] ?? 0);
}

// Outward Stock Query (stock going out - sales, transfers, etc.)
$outward_where = "s.status = 1 AND s.stock_type = 'outward'";
if ($product_id > 0) {
    $outward_where .= " AND s.product_id = $product_id";
}
if ($characteristic_id > 0) {
    $outward_where .= " AND s.product_characteristic_id = $characteristic_id";
}
$outward_where .= $adv_where_append;
$outward_where .= isset($stock_history_metal_scope_sql) ? $stock_history_metal_scope_sql : '';

$outward_query = "
    SELECT 
        MAX(sub.id) as id,
        MAX(sub.product_id) as product_id,
        MAX(sub.product_characteristic_id) as product_characteristic_id,
        MAX(sub.branch_id) as branch_id,
        MAX(sub.metal_id) as metal_id,
        MAX(sub.status) as status,
        MAX(sub.product_name) as product_name,
        MAX(sub.metal_name) as metal_name,
        MAX(sub.branch_name) as branch_name,
        MAX(sub.hsn) as hsn,
        MAX(sub.sku_code) as sku_code,
        MAX(sub.article) as article,
        MAX(sub.characteristic_carat) as characteristic_carat,
        COALESCE(MAX(sub.pi_net_total), MAX(sub.pi_grand_total), SUM(sub.value)) as net_amt,
        DATE(MIN(sub.created_at)) as transaction_date,
        SUM(sub.display_qty) as qty,
        SUM(sub.display_weight) as gross_wt,
        AVG(sub.opening_purity) as purity,
        (SUM(sub.display_weight) * (CASE WHEN AVG(sub.opening_purity) <= 1 THEN AVG(sub.opening_purity) ELSE AVG(sub.opening_purity) / 100 END)) as pure_wt,
        SUM(sub.final_weight) as final_weight,
        AVG(sub.rate) as rate,
        COALESCE(MAX(sub.pi_net_total), MAX(sub.pi_grand_total), SUM(sub.value)) as amount,
        COALESCE(MAX(sub.s_barcode), MAX(sub.sj_barcode), MAX(sub.pii_barcode), '') as barcode,
        COALESCE(MAX(sub.pi_id), 0) as invoice_id,
        COALESCE(MAX(sub.sj_item_id), 0) as sj_attach_item_id,
        '' as rfid,
        '' as location,
        COALESCE(MAX(sub.sj_invoice_no), MAX(sub.mi_invoice_no), MAX(sub.jwo_invoice_no), MAX(sub.pi_invoice_no), MAX(sub.pv_voucher_no), '') as against_invoice_no,
        COALESCE(MAX(sub.pi_invoice_no), '') as invoice_no,
        CASE
            WHEN MAX(sub.s_ref_type) = 'material_issue' THEN 'Material Issue'
            WHEN MAX(sub.s_ref_type) = 'jobwork_diamond_issue' THEN 'Jobwork Transfer'
            WHEN MAX(sub.s_ref_type) = 'jobwork_order' THEN 'Job Work Order'
            WHEN MAX(sub.s_ref_type) = 'payment_voucher' THEN 'Payment Voucher'
            WHEN COALESCE(MAX(sub.sj_invoice_no), '') = '' AND MAX(sub.sj_item_id) IS NULL THEN 'Outward'
            WHEN LOWER(TRIM(COALESCE(MAX(sub.sj_voucher_type), ''))) = 'product_opening' THEN 'Product Opening'
            WHEN TRIM(COALESCE(MAX(sub.sj_voucher_type), '')) IN ('Purchase Invoice', 'purchase_invoice') THEN 'Purchase Invoice'
            WHEN TRIM(COALESCE(MAX(sub.sj_voucher_type), '')) != '' THEN TRIM(MAX(sub.sj_voucher_type))
            WHEN MAX(sub.sj_item_id) IS NOT NULL OR COALESCE(MAX(sub.sj_invoice_no), '') != '' THEN 'Stock Journal'
            ELSE 'Outward'
        END as type_of_voucher,
        CASE
            WHEN MAX(sub.s_ref_type) = 'material_issue' THEN 'Material Issue'
            WHEN MAX(sub.s_ref_type) = 'jobwork_diamond_issue' THEN 'Jobwork Transfer'
            WHEN MAX(sub.s_ref_type) = 'jobwork_order' THEN 'Job Work Order'
            WHEN MAX(sub.s_ref_type) = 'payment_voucher' THEN 'Payment Voucher'
            WHEN COALESCE(MAX(sub.sj_invoice_no), '') = '' AND MAX(sub.sj_item_id) IS NULL THEN 'Outward'
            WHEN LOWER(TRIM(COALESCE(MAX(sub.sj_voucher_type), ''))) = 'product_opening' THEN 'Product Opening'
            WHEN TRIM(COALESCE(MAX(sub.sj_voucher_type), '')) IN ('Purchase Invoice', 'purchase_invoice') THEN 'Purchase Invoice'
            WHEN TRIM(COALESCE(MAX(sub.sj_voucher_type), '')) != '' THEN TRIM(MAX(sub.sj_voucher_type))
            WHEN MAX(sub.sj_item_id) IS NOT NULL OR COALESCE(MAX(sub.sj_invoice_no), '') != '' THEN 'Stock Journal'
            ELSE 'Outward'
        END as voucher_type,
        MAX(sub.stock_type) as vouch,
        '' as requested_qty,
        '' as requested_wt,
        0.00 as stone_wt,
        0.00 as diamond_wt,
        0.00 as less_wt,
        0.00 as purity_wt,
        0.00 as wastage_per,
        0.00 as wastage_wt,
        SUM(sub.display_weight) as net_wt,
        0.00 as alloy_wt,
        SUM(sub.final_weight) as final_wt,
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
        0.00 as purchase
    FROM (
        SELECT 
            s.id,
            s.product_id,
            s.product_characteristic_id,
            s.branch_id,
            s.metal_id,
            s.status,
            IFNULL(NULLIF(s.current_qty, 0), s.opening_qty) AS display_qty,
            IFNULL(NULLIF(s.current_weight, 0), s.opening_weight) AS display_weight,
            s.current_qty,
            s.current_weight,
            s.opening_purity,
            s.final_weight,
            s.rate,
            s.value,
            s.created_at,
            s.stock_type,
            s.barcode as s_barcode,
            COALESCE(NULLIF(TRIM(s.reference_type), ''), '') as s_ref_type,
            s.reference_id as s_ref_id,
            mi_sh.material_issue_no as mi_invoice_no,
            COALESCE(NULLIF(TRIM(jwo_sh.jobwork_no), ''), NULLIF(TRIM(jwo_sh.jobwork_queue_no), '')) as jwo_invoice_no,
            p.name as product_name,
            p.article as article,
            m.display_name as metal_name,
            b.name as branch_name,
            pc.hsn,
            pc.sku_code,
            COALESCE(pc.carat, 0) as characteristic_carat,
            sj.item_id as sj_item_id,
            sj.voucher_type as sj_voucher_type,
            sj.sj_invoice_no as sj_invoice_no,
            sj.barcode as sj_barcode,
            pii.barcode as pii_barcode,
            pi.id as pi_id,
            pi.net_total as pi_net_total,
            pi.grand_total as pi_grand_total,
            pi.invoice_no as pi_invoice_no,
            pv_sh.voucher_no as pv_voucher_no
        FROM tbl_stock s
        LEFT JOIN tbl_products p ON s.product_id = p.id
        LEFT JOIN tbl_metal m ON s.metal_id = m.id
        LEFT JOIN tbl_branches b ON s.branch_id = b.id
        LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
        LEFT JOIN tbl_material_issues mi_sh ON (
            s.stock_type = 'outward'
            AND s.reference_type = 'material_issue'
            AND mi_sh.id = s.reference_id
        )
        LEFT JOIN tbl_jobwork_orders jwo_sh ON (
            s.stock_type = 'outward'
            AND (
                (s.reference_type = 'jobwork_diamond_issue' AND jwo_sh.id = s.reference_id)
                OR (s.reference_type = 'jobwork_order' AND jwo_sh.id = s.reference_id)
            )
        )
        LEFT JOIN tbl_payment_vouchers pv_sh ON (
            s.stock_type = 'outward'
            AND s.reference_type = 'payment_voucher'
            AND pv_sh.id = s.reference_id
        )
        LEFT JOIN (
            SELECT s_inner.id as stock_id,
                (SELECT sj_in.id FROM tbl_stock_journal sj_in
                 WHERE sj_in.product_id = s_inner.product_id
                 AND (sj_in.product_characteristic_id = s_inner.product_characteristic_id OR (sj_in.product_characteristic_id IS NULL AND s_inner.product_characteristic_id IS NULL))
                 AND DATE(sj_in.sj_date) = DATE(s_inner.created_at)
                 AND ABS(TIMESTAMPDIFF(SECOND, sj_in.created_at, s_inner.created_at)) <= 5
                 AND sj_in.status = 'active'
                 AND (s_inner.barcode IS NULL OR s_inner.barcode = '' OR sj_in.barcode COLLATE utf8mb4_unicode_ci = s_inner.barcode COLLATE utf8mb4_unicode_ci)
                 ORDER BY sj_in.id ASC LIMIT 1) as sj_id
            FROM tbl_stock s_inner
            WHERE s_inner.status = 1 AND s_inner.stock_type = 'outward'
        ) s_sj ON s.id = s_sj.stock_id AND s.stock_type = 'outward'
        LEFT JOIN tbl_stock_journal sj ON sj.id = s_sj.sj_id AND sj.status = 'active'
        LEFT JOIN tbl_purchase_invoice_items pii ON sj.item_id = pii.id
        LEFT JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
        WHERE $outward_where
    ) sub
    GROUP BY 
        sub.id,
        sub.product_id,
        sub.product_characteristic_id,
        sub.product_name,
        sub.metal_name,
        sub.branch_name,
        sub.hsn,
        sub.sku_code,
        sub.article,
        sub.characteristic_carat
    ORDER BY transaction_date DESC
";

$outward_total_record = getRecord("SELECT COUNT(*) as total FROM tbl_stock s WHERE $outward_where");
$outward_total = $outward_total_record ? (int)$outward_total_record['total'] : 0;
$outward_total_pages = $outward_total > 0 ? ceil($outward_total / $outward_per_page) : 1;

$outward_data = getList($outward_query . " LIMIT $outward_per_page OFFSET $outward_offset");

// Calculate totals from the main query results to match displayed values
$outward_totals_all = getList($outward_query);
$outward_totals = [
    'total_net_amt' => 0,
    'total_qty' => 0,
    'total_gross_wt' => 0,
    'total_pure_wt' => 0,
    'total_requested_qty' => 0,
    'total_requested_wt' => 0,
    'total_stone_wt' => 0,
    'total_diamond_wt' => 0,
    'total_less_wt' => 0,
    'total_purity_wt' => 0,
    'total_wastage_wt' => 0,
    'total_net_wt' => 0,
    'total_alloy_wt' => 0,
    'total_final_wt' => 0,
    'total_standard_wt' => 0,
    'total_actual_wt' => 0,
    'total_national_wt' => 0,
    'total_making_amt' => 0,
    'total_amount' => 0,
    'total_packet_wt' => 0,
    'total_packet_length' => 0,
    'total_net_amt_with_tax' => 0,
    'total_tax_amt' => 0,
    'total_discount_amt' => 0,
    'total_metal_value' => 0,
    'total_purchase' => 0
];
foreach ($outward_totals_all as $tot_row) {
    $outward_totals['total_net_amt'] += (float)($tot_row['net_amt'] ?? 0);
    $outward_totals['total_qty'] += (float)($tot_row['qty'] ?? 0);
    $outward_totals['total_gross_wt'] += (float)($tot_row['gross_wt'] ?? 0);
    $outward_totals['total_pure_wt'] += (float)($tot_row['pure_wt'] ?? 0);
    $outward_totals['total_requested_qty'] += (float)($tot_row['requested_qty'] ?? 0);
    $outward_totals['total_requested_wt'] += (float)($tot_row['requested_wt'] ?? 0);
    $outward_totals['total_stone_wt'] += (float)($tot_row['stone_wt'] ?? 0);
    $outward_totals['total_diamond_wt'] += (float)($tot_row['diamond_wt'] ?? 0);
    $outward_totals['total_less_wt'] += (float)($tot_row['less_wt'] ?? 0);
    $outward_totals['total_purity_wt'] += (float)($tot_row['purity_wt'] ?? 0);
    $outward_totals['total_wastage_wt'] += (float)($tot_row['wastage_wt'] ?? 0);
    $outward_totals['total_net_wt'] += (float)($tot_row['net_wt'] ?? 0);
    $outward_totals['total_alloy_wt'] += (float)($tot_row['alloy_wt'] ?? 0);
    $outward_totals['total_final_wt'] += (float)($tot_row['final_wt'] ?? 0);
    $outward_totals['total_standard_wt'] += (float)($tot_row['standard_wt'] ?? 0);
    $outward_totals['total_actual_wt'] += (float)($tot_row['actual_wt'] ?? 0);
    $outward_totals['total_national_wt'] += (float)($tot_row['national_wt'] ?? 0);
    $outward_totals['total_making_amt'] += (float)($tot_row['making_amt'] ?? 0);
    $outward_totals['total_amount'] += (float)($tot_row['amount'] ?? 0);
    $outward_totals['total_packet_wt'] += (float)($tot_row['packet_wt'] ?? 0);
    $outward_totals['total_packet_length'] += (float)($tot_row['packet_length'] ?? 0);
    $outward_totals['total_net_amt_with_tax'] += (float)($tot_row['net_amt_with_tax'] ?? 0);
    $outward_totals['total_tax_amt'] += (float)($tot_row['tax_amt'] ?? 0);
    $outward_totals['total_discount_amt'] += (float)($tot_row['discount_amt'] ?? 0);
    $outward_totals['total_metal_value'] += (float)($tot_row['metal_value'] ?? 0);
    $outward_totals['total_purchase'] += (float)($tot_row['purchase'] ?? 0);
}
