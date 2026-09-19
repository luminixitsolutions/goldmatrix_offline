-- Fix receipt voucher ledger rows where balance_gold_pure was stored as 0.
-- Run once after deploying the save-receipt-voucher.php fix.
-- Sets balance_gold_pure = previous row's balance_gold_pure + this row's debit_gold_pure.

UPDATE tbl_customer_ledger l
JOIN (
    SELECT l2.id,
           (SELECT COALESCE(m.balance_gold_pure, 0)
            FROM tbl_customer_ledger m
            WHERE m.customer_id = l2.customer_id AND m.status = 1 AND m.id < l2.id
            ORDER BY m.id DESC LIMIT 1) + COALESCE(l2.debit_gold_pure, 0) AS new_balance_gold_pure
    FROM tbl_customer_ledger l2
    WHERE l2.transaction_type = 'receipt_voucher'
      AND COALESCE(l2.debit_gold_pure, 0) > 0
      AND COALESCE(l2.balance_gold_pure, 0) = 0
) calc ON calc.id = l.id
SET l.balance_gold_pure = calc.new_balance_gold_pure;
