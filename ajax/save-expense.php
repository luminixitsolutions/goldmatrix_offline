<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

// Create expense tables if they do not exist
$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_expenses'");
if (!$tbl || mysqli_num_rows($tbl) == 0) {
    $create_expenses = "CREATE TABLE IF NOT EXISTS `tbl_expenses` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `expense_no` varchar(50) NOT NULL,
        `with_tax` tinyint(1) DEFAULT 1,
        `ledger_id` int(11) DEFAULT NULL,
        `ledger_name` varchar(255) NOT NULL,
        `against_of` varchar(255) DEFAULT NULL,
        `currency` varchar(10) DEFAULT 'INR',
        `exchange_rate` decimal(15,6) DEFAULT 1.000000,
        `expense_date` date NOT NULL,
        `due_date` date DEFAULT NULL,
        `ref_no` varchar(100) DEFAULT NULL,
        `sales_person` varchar(255) DEFAULT NULL,
        `layaways` varchar(100) DEFAULT NULL,
        `fixing_type` varchar(50) DEFAULT 'Standard',
        `previous_balance` decimal(15,2) DEFAULT 0.00,
        `previous_gold` decimal(15,2) DEFAULT 0.00,
        `previous_silver` decimal(15,2) DEFAULT 0.00,
        `subtotal` decimal(15,2) DEFAULT 0.00,
        `net_total` decimal(15,2) DEFAULT 0.00,
        `discount_percent` decimal(10,2) DEFAULT 0.00,
        `discount_amt` decimal(15,2) DEFAULT 0.00,
        `grand_total` decimal(15,2) DEFAULT 0.00,
        `round_off` decimal(15,2) DEFAULT 0.00,
        `paid_amt` decimal(15,2) DEFAULT 0.00,
        `balance_amt` decimal(15,2) DEFAULT 0.00,
        `comment` text DEFAULT NULL,
        `status` varchar(20) DEFAULT 'draft',
        `created_by` int(11) DEFAULT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `expense_no` (`expense_no`),
        KEY `ledger_id` (`ledger_id`),
        KEY `expense_date` (`expense_date`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!mysqli_query($conn, $create_expenses)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not create tbl_expenses: ' . mysqli_error($conn)]);
        exit;
    }
    $create_items = "CREATE TABLE IF NOT EXISTS `tbl_expense_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `expense_id` int(11) NOT NULL,
        `category` varchar(255) DEFAULT NULL,
        `description` text DEFAULT NULL,
        `amount` decimal(15,2) DEFAULT 0.00,
        `tax_rate` decimal(10,2) DEFAULT 0.00,
        `tax_amount` decimal(15,2) DEFAULT 0.00,
        `tax_with_amount` decimal(15,2) DEFAULT 0.00,
        `sort_order` int(11) DEFAULT 0,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `expense_id` (`expense_id`),
        CONSTRAINT `fk_expense_items_expense` FOREIGN KEY (`expense_id`) REFERENCES `tbl_expenses` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!mysqli_query($conn, $create_items)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not create tbl_expense_items: ' . mysqli_error($conn)]);
        exit;
    }
    $create_payments = "CREATE TABLE IF NOT EXISTS `tbl_expense_payments` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `expense_id` int(11) NOT NULL,
        `payment_type` varchar(50) NOT NULL,
        `deposit_into` varchar(100) DEFAULT NULL,
        `diamond_category` varchar(100) DEFAULT NULL,
        `transaction_no` varchar(100) DEFAULT NULL,
        `transfer_from` varchar(255) DEFAULT NULL,
        `cheque_date` date DEFAULT NULL,
        `amount` decimal(15,2) NOT NULL,
        `card_no` varchar(50) DEFAULT NULL,
        `status` tinyint(1) DEFAULT 1,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `expense_id` (`expense_id`),
        CONSTRAINT `fk_expense_payments_expense` FOREIGN KEY (`expense_id`) REFERENCES `tbl_expenses` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!mysqli_query($conn, $create_payments)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not create tbl_expense_payments: ' . mysqli_error($conn)]);
        exit;
    }
}

mysqli_begin_transaction($conn);

try {
    $user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);

    $expense_no = esc($_POST['expense_no'] ?? '');
    $expense_id = isset($_POST['expense_id']) ? (int)$_POST['expense_id'] : 0;
    $with_tax = isset($_POST['with_tax']) && $_POST['with_tax'] ? 1 : 0;
    $ledger_id = isset($_POST['ledger_id']) ? (int)$_POST['ledger_id'] : 0;
    $ledger_name = esc($_POST['ledger_name'] ?? $_POST['customer_name'] ?? '');
    $against_of = esc($_POST['against_of'] ?? '');
    $currency = esc($_POST['currency'] ?? 'INR');
    $exchange_rate = (float)($_POST['exchange_rate'] ?? 1);
    $expense_date = esc($_POST['expense_date'] ?? $_POST['order_date'] ?? date('Y-m-d'));
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

    if (empty($expense_no)) {
        $last = getRecord("SELECT expense_no FROM tbl_expenses ORDER BY id DESC LIMIT 1");
        if ($last && $last['expense_no']) {
            $num = (int)preg_replace('/[^0-9]/', '', $last['expense_no']);
            $expense_no = 'EP-' . ($num + 1);
        } else {
            $expense_no = 'EP-1';
        }
    }

    $is_update = ($expense_id > 0);

    if ($is_update) {
        $cur = getRecord("SELECT expense_no FROM tbl_expenses WHERE id = $expense_id");
        $cur_no = $cur ? $cur['expense_no'] : '';
        if ($expense_no !== $cur_no) {
            $ex = getRecord("SELECT id FROM tbl_expenses WHERE expense_no = '$expense_no' AND id != $expense_id");
            if ($ex) throw new Exception("Expense No '$expense_no' already exists.");
        }
        mysqli_query($conn, "
            UPDATE tbl_expenses SET
                expense_no = '$expense_no',
                with_tax = $with_tax,
                ledger_id = " . ($ledger_id > 0 ? $ledger_id : 'NULL') . ",
                ledger_name = '$ledger_name',
                against_of = " . ($against_of ? "'$against_of'" : 'NULL') . ",
                currency = '$currency',
                exchange_rate = $exchange_rate,
                expense_date = '$expense_date',
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
            WHERE id = $expense_id
        ");
        if (mysqli_error($conn)) throw new Exception('Update failed: ' . mysqli_error($conn));
        mysqli_query($conn, "DELETE FROM tbl_expense_items WHERE expense_id = $expense_id");
        mysqli_query($conn, "DELETE FROM tbl_expense_payments WHERE expense_id = $expense_id");
    } else {
        mysqli_query($conn, "
            INSERT INTO tbl_expenses (
                expense_no, with_tax, ledger_id, ledger_name, against_of, currency, exchange_rate,
                expense_date, due_date, ref_no, sales_person, layaways, fixing_type,
                previous_balance, previous_gold, previous_silver,
                subtotal, net_total, discount_percent, discount_amt, grand_total, round_off, paid_amt, balance_amt,
                comment, status, created_by, created_at
            ) VALUES (
                '$expense_no', $with_tax, " . ($ledger_id > 0 ? $ledger_id : 'NULL') . ", '$ledger_name',
                " . ($against_of ? "'$against_of'" : 'NULL') . ", '$currency', $exchange_rate,
                '$expense_date', " . ($due_date ? "'$due_date'" : 'NULL') . ",
                " . ($ref_no ? "'$ref_no'" : 'NULL') . ", " . ($sales_person ? "'$sales_person'" : 'NULL') . ",
                " . ($layaways ? "'$layaways'" : 'NULL') . ", '$fixing_type',
                $previous_balance, $previous_gold, $previous_silver,
                $subtotal, $net_total, $discount_percent, $discount_amt, $grand_total, $round_off, $paid_amt, $balance_amt,
                " . ($comment ? "'$comment'" : 'NULL') . ", 'draft', " . ($user_id ? $user_id : 'NULL') . ", NOW()
            )
        ");
        if (mysqli_error($conn)) throw new Exception('Insert failed: ' . mysqli_error($conn));
        $expense_id = mysqli_insert_id($conn);
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
            $iq = "INSERT INTO tbl_expense_items (expense_id, category, description, amount, tax_rate, tax_amount, tax_with_amount, sort_order, created_at)
                VALUES ($expense_id, " . ($category ? "'$category'" : 'NULL') . ", " . ($description ? "'$description'" : 'NULL') . ", $amount, $tax_rate, $tax_amount, $tax_with_amount, $sort, NOW())";
            if (!mysqli_query($conn, $iq)) throw new Exception('Item insert failed: ' . mysqli_error($conn));
        }
    }

    $payments = [];
    if (isset($_POST['payments'])) {
        $payments = is_string($_POST['payments']) ? json_decode($_POST['payments'], true) : $_POST['payments'];
    }
    if (!empty($payments) && is_array($payments)) {
        foreach ($payments as $p) {
            $amount = (float)($p['amount'] ?? 0);
            $payment_type = esc($p['payment_type'] ?? 'Cash');
            $deposit_into = esc($p['deposit_into'] ?? '');
            $diamond_category = esc($p['diamond_category'] ?? '');
            $transaction_no = esc($p['transaction_no'] ?? '');
            $transfer_from = esc($p['transfer_from'] ?? '');
            $cheque_date = !empty($p['cheque_date']) ? esc($p['cheque_date']) : null;
            $card_no = esc($p['card_no'] ?? '');

            $pq = "INSERT INTO tbl_expense_payments (expense_id, payment_type, deposit_into, diamond_category, transaction_no, transfer_from, cheque_date, amount, card_no, status, created_at)
                VALUES ($expense_id, '$payment_type',
                " . ($deposit_into ? "'$deposit_into'" : 'NULL') . ",
                " . ($diamond_category ? "'$diamond_category'" : 'NULL') . ",
                " . ($transaction_no ? "'$transaction_no'" : 'NULL') . ",
                " . ($transfer_from ? "'$transfer_from'" : 'NULL') . ",
                " . ($cheque_date ? "'$cheque_date'" : 'NULL') . ",
                $amount,
                " . ($card_no ? "'$card_no'" : 'NULL') . ", 1, NOW())";
            if (!mysqli_query($conn, $pq)) throw new Exception('Payment insert failed: ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);
    echo json_encode([
        'status' => 'success',
        'message' => 'Expense saved successfully',
        'expense_id' => $expense_id,
        'expense_no' => $expense_no
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
