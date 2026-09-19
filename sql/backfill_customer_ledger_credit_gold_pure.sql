-- Backfill credit_gold_pure (and balance_gold_pure) for existing Hedging purchase-invoice ledger rows
-- from tbl_purchase_invoice_items.purity_weight (gold items only).
-- Run AFTER alter_tbl_customer_ledger_add_gold_pure.sql.

-- Step 1: Update credit_gold_pure on credit-side Hedging PI rows from invoice items (gold only)
UPDATE tbl_customer_ledger l
INNER JOIN (
    SELECT
        p.invoice_id,
        SUM(CASE WHEN COALESCE(p.purity_weight, 0) > 0 THEN p.purity_weight ELSE p.net_weight END) AS pure_wt
    FROM tbl_purchase_invoice_items p
    LEFT JOIN tbl_product_characteristics pc ON pc.product_id = p.product_id AND pc.status = 1
    LEFT JOIN tbl_metal m ON m.id = pc.metal_id
    WHERE (
        LOWER(COALESCE(m.display_name, m.system_name, '')) LIKE '%gold%'
        OR (p.product_name IS NOT NULL AND LOWER(p.product_name) LIKE '%gold%')
    )
    GROUP BY p.invoice_id
) i ON i.invoice_id = l.transaction_id
SET l.credit_gold_pure = i.pure_wt
WHERE l.transaction_type = 'purchase_invoice'
  AND l.credit_gold > 0
  AND COALESCE(l.description, '') LIKE '%(Hedging)%';

-- Step 2: Recompute balance_gold_pure for affected ledger (run per customer if needed)
-- Optional: you can re-save the purchase invoice from the UI to regenerate ledger rows with correct balance_gold_pure.
