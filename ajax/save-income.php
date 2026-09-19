<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_income_invoice_schema.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

if (!isset($conn) || !($conn instanceof mysqli)) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection unavailable']);
    exit;
}

if (!auragold_ensure_income_invoice_tables($conn)) {
    echo json_encode(['status' => 'error', 'message' => 'Could not create income invoice tables: ' . mysqli_error($conn)]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);

    $income_no = esc($_POST['income_no'] ?? '');
    $income_id = isset($_POST['income_id']) ? (int)$_POST['income_id'] : 0;
    $with_tax = isset($_POST['with_tax']) && $_POST['with_tax'] ? 1 : 0;
    $ledger_id = isset($_POST['ledger_id']) ? (int)$_POST['ledger_id'] : 0;
    $ledger_name = esc($_POST['ledger_name'] ?? $_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'AED');
    $exchange_rate = (float)($_POST['exchange_rate'] ?? 1);
    $income_date = esc($_POST['income_date'] ?? $_POST['order_date'] ?? date('Y-m-d'));
    $due_date = esc($_POST['due_date'] ?? '');
    $ref_no = esc($_POST['ref_no'] ?? '');
    $sales_person = esc($_POST['sales_person'] ?? '');
    $layaways = esc($_POST['layaways'] ?? '');
    $fixing_type = esc($_POST['fixing_type'] ?? 'Standard');
    $comment = esc($_POST['comment'] ?? '');

    $previous_balance = (float)($_POST['previous_balance'] ?? 0);
    $previous_gold = (float)($_POST['previous_gold'] ?? 0);
    $previous_silver = (float)($_POST['previous_silver'] ?? 0);
    $subtotal = (float)($_POST['subtotal'] ?? 0);
    $net_total = (float)($_POST['net_total'] ?? 0);
    $discount_percent = (float)($_POST['discount_percent'] ?? 0);
    $discount_amt = (float)($_POST['discount_amt'] ?? 0);
    $grand_total = (float)($_POST['grand_total'] ?? 0);
    $round_off = (float)($_POST['round_off'] ?? 0);
    $paid_amt = (float)($_POST['paid_amt'] ?? 0);
    $balance_amt = (float)($_POST['balance_amt'] ?? 0);

    if (empty($ledger_name)) {
        throw new Exception('Name is required');
    }

    if (empty($income_no)) {
        $last = getRecord("SELECT income_no FROM tbl_incomes ORDER BY id DESC LIMIT 1");
        if ($last && $last['income_no']) {
            $num = (int)preg_replace('/[^0-9]/', '', $last['income_no']);
            $income_no = 'INI-' . ($num + 1);
        } else {
            $income_no = 'INI-1';
        }
    }

    $is_update = ($income_id > 0);

    if ($is_update) {
        $cur = getRecord("SELECT income_no FROM tbl_incomes WHERE id = $income_id");
        $cur_no = $cur ? $cur['income_no'] : '';
        if ($income_no !== $cur_no) {
            $ex = getRecord("SELECT id FROM tbl_incomes WHERE income_no = '$income_no' AND id != $income_id");
            if ($ex) throw new Exception("Income No '$income_no' already exists.");
        }
        mysqli_query($conn, "
            UPDATE tbl_incomes SET
                income_no = '$income_no',
                with_tax = $with_tax,
                ledger_id = " . ($ledger_id > 0 ? $ledger_id : 'NULL') . ",
                ledger_name = '$ledger_name',
                against_of = " . ($against_of ? "'$against_of'" : 'NULL') . ",
                currency = '$currency',
                exchange_rate = $exchange_rate,
                income_date = '$income_date',
                due_date = " . ($due_date ? "'$due_date'" : 'NULL') . ",
                ref_no = " . ($ref_no ? "'$ref_no'" : 'NULL') . ",
                sales_person = " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                layaways = " . ($layaways ? "'$layaways'" : 'NULL') . ",
                fixing_type = '$fixing_type',
                previous_balance = $previous_balance,
                previous_gold = $previous_gold,
                previous_silver = $previous_silver,
                subtotal = $subtotal,
                net_total = $net_total,
                discount_percent = $discount_percent,
                discount_amt = $discount_amt,
                grand_total = $grand_total,
                round_off = $round_off,
                paid_amt = $paid_amt,
                balance_amt = $balance_amt,
                comment = " . ($comment ? "'$comment'" : 'NULL') . ",
                updated_at = NOW()
            WHERE id = $income_id
        ");
        if (mysqli_error($conn)) throw new Exception('Update failed: ' . mysqli_error($conn));
        mysqli_query($conn, "DELETE FROM tbl_income_items WHERE income_id = $income_id");
        mysqli_query($conn, "DELETE FROM tbl_income_receipts WHERE income_id = $income_id");
    } else {
        mysqli_query($conn, "
            INSERT INTO tbl_incomes (
                income_no, with_tax, ledger_id, ledger_name, against_of, currency, exchange_rate,
                income_date, due_date, ref_no, sales_person, layaways, fixing_type,
                previous_balance, previous_gold, previous_silver,
                subtotal, net_total, discount_percent, discount_amt, grand_total, round_off, paid_amt, balance_amt,
                comment, status, created_by, created_at
            ) VALUES (
                '$income_no', $with_tax, " . ($ledger_id > 0 ? $ledger_id : 'NULL') . ", '$ledger_name',
                " . ($against_of ? "'$against_of'" : 'NULL') . ", '$currency', $exchange_rate,
                '$income_date', " . ($due_date ? "'$due_date'" : 'NULL') . ",
                " . ($ref_no ? "'$ref_no'" : 'NULL') . ", " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                " . ($layaways ? "'$layaways'" : 'NULL') . ", '$fixing_type',
                $previous_balance, $previous_gold, $previous_silver,
                $subtotal, $net_total, $discount_percent, $discount_amt, $grand_total, $round_off, $paid_amt, $balance_amt,
                " . ($comment ? "'$comment'" : 'NULL') . ", 'draft', " . ($user_id ? $user_id : 'NULL') . ", NOW()
            )
        ");
        if (mysqli_error($conn)) throw new Exception('Insert failed: ' . mysqli_error($conn));
        $income_id = mysqli_insert_id($conn);
    }

    $items = [];
    if (isset($_POST['items'])) {
        $items = is_string($_POST['items']) ? json_decode($_POST['items'], true) : $_POST['items'];
    }
    if (!empty($items) && is_array($items)) {
        $sort = 0;
        foreach ($items as $item) {
            $category = esc($item['category'] ?? '');
            $description = esc($item['description'] ?? '');
            $amount = (float)($item['amount'] ?? 0);
            $tax_rate = (float)($item['tax_rate'] ?? 0);
            $tax_amount = (float)($item['tax_amount'] ?? 0);
            $tax_with_amount = (float)($item['tax_with_amount'] ?? ($amount + $tax_amount));
            $sort++;
            $iq = "INSERT INTO tbl_income_items (income_id, category, description, amount, tax_rate, tax_amount, tax_with_amount, sort_order, created_at)
                VALUES ($income_id, " . ($category ? "'$category'" : 'NULL') . ", " . ($description ? "'$description'" : 'NULL') . ", $amount, $tax_rate, $tax_amount, $tax_with_amount, $sort, NOW())";
            if (!mysqli_query($conn, $iq)) throw new Exception('Item insert failed: ' . mysqli_error($conn));
        }
    }

    $receipts = [];
    if (isset($_POST['receipts'])) {
        $receipts = is_string($_POST['receipts']) ? json_decode($_POST['receipts'], true) : $_POST['receipts'];
    } elseif (isset($_POST['payments'])) {
        $receipts = is_string($_POST['payments']) ? json_decode($_POST['payments'], true) : $_POST['payments'];
    }
    if (!empty($receipts) && is_array($receipts)) {
        foreach ($receipts as $p) {
            $amount = (float)($p['amount'] ?? 0);
            $payment_type = esc($p['payment_type'] ?? 'Cash');
            $deposit_into = esc($p['deposit_into'] ?? '');
            $diamond_category = esc($p['diamond_category'] ?? '');
            $transaction_no = esc($p['transaction_no'] ?? '');
            $transfer_from = esc($p['transfer_from'] ?? '');
            $cheque_date = !empty($p['cheque_date']) ? esc($p['cheque_date']) : null;
            $card_no = esc($p['card_no'] ?? '');

            $pq = "INSERT INTO tbl_income_receipts (income_id, payment_type, deposit_into, diamond_category, transaction_no, transfer_from, cheque_date, amount, card_no, status, created_at)
                VALUES ($income_id, '$payment_type',
                " . ($deposit_into ? "'$deposit_into'" : 'NULL') . ",
                " . ($diamond_category ? "'$diamond_category'" : 'NULL') . ",
                " . ($transaction_no ? "'$transaction_no'" : 'NULL') . ",
                " . ($transfer_from ? "'$transfer_from'" : 'NULL') . ",
                " . ($cheque_date ? "'$cheque_date'" : 'NULL') . ",
                $amount,
                " . ($card_no ? "'$card_no'" : 'NULL') . ", 1, NOW())";
            if (!mysqli_query($conn, $pq)) throw new Exception('Receipt insert failed: ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);
    echo json_encode([
        'status' => 'success',
        'message' => 'Income saved successfully',
        'income_id' => $income_id,
        'income_no' => $income_no
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
