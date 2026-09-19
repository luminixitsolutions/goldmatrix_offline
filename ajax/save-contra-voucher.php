<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold_ensure_payment_mode_ledger.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Create contra voucher tables if they do not exist
$t1 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_contra_vouchers'");
if (!$t1 || mysqli_num_rows($t1) === 0) {
    $create_main = "CREATE TABLE IF NOT EXISTS `tbl_contra_vouchers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `voucher_no` varchar(50) NOT NULL,
        `voucher_date` date NOT NULL,
        `total_amount` decimal(15,2) DEFAULT 0.00,
        `comment` text DEFAULT NULL,
        `status` varchar(20) DEFAULT 'saved',
        `created_by` int(11) DEFAULT NULL,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `voucher_no` (`voucher_no`),
        KEY `voucher_date` (`voucher_date`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!mysqli_query($conn, $create_main)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not create tbl_contra_vouchers: ' . mysqli_error($conn)]);
        exit;
    }
}
$t2 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_contra_voucher_items'");
if (!$t2 || mysqli_num_rows($t2) === 0) {
    $create_items = "CREATE TABLE IF NOT EXISTS `tbl_contra_voucher_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `voucher_id` int(11) NOT NULL,
        `bank_cash_ac` varchar(100) NOT NULL,
        `ref_no` varchar(100) DEFAULT NULL,
        `ref_date` date DEFAULT NULL,
        `transaction_type` varchar(20) NOT NULL DEFAULT 'withdrawal',
        `amount` decimal(15,2) DEFAULT 0.00,
        `comment` text DEFAULT NULL,
        `status` tinyint(1) DEFAULT 1,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `voucher_id` (`voucher_id`),
        CONSTRAINT `fk_contra_voucher_items_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `tbl_contra_vouchers` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!mysqli_query($conn, $create_items)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not create tbl_contra_voucher_items: ' . mysqli_error($conn)]);
        exit;
    }
}

$has_cv_branch = function_exists('auragold_ensure_table_branch_id_column')
    ? auragold_ensure_table_branch_id_column($conn, 'tbl_contra_vouchers')
    : false;
$hdr_branch = function_exists('auragold_transaction_header_branch_id')
    ? (int) auragold_transaction_header_branch_id()
    : 0;
$eff_branch = function_exists('auragold_effective_branch_id')
    ? (int) auragold_effective_branch_id()
    : 0;
$cv_branch = $hdr_branch > 0 ? $hdr_branch : $eff_branch;

mysqli_begin_transaction($conn);

try {
    $voucher_id = isset($_POST['voucher_id']) ? (int) $_POST['voucher_id'] : 0;
    $voucher_no = esc($_POST['voucher_no'] ?? '');
    $voucher_date = esc($_POST['voucher_date'] ?? date('Y-m-d'));
    $comment = esc($_POST['comment'] ?? '');
    $header_bank = trim((string) ($_POST['bank_cash_ac'] ?? ''));
    $header_tt = strtolower(trim((string) ($_POST['transaction_type'] ?? 'withdrawal')));
    if (!in_array($header_tt, ['deposit', 'withdrawal'], true)) {
        $header_tt = 'withdrawal';
    }
    $items = isset($_POST['items']) ? $_POST['items'] : [];

    if (is_string($items)) {
        $items = json_decode($items, true);
    }
    if (!is_array($items)) {
        $items = [];
    }

    if ($voucher_no === '') {
        throw new Exception('Voucher number is required.');
    }

    $clean_items = [];
    $total_amount = 0.0;
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $bank = trim((string) ($it['bank_cash_ac'] ?? ''));
        $amount = (float) ($it['amount'] ?? 0);
        $tt = strtolower(trim((string) ($it['transaction_type'] ?? 'withdrawal')));
        if (!in_array($tt, ['deposit', 'withdrawal'], true)) {
            $tt = 'withdrawal';
        }
        if ($bank === '' || $amount <= 0) {
            continue;
        }
        $clean_items[] = [
            'bank_cash_ac' => $bank,
            'ref_no' => trim((string) ($it['ref_no'] ?? '')),
            'ref_date' => trim((string) ($it['ref_date'] ?? '')),
            'transaction_type' => $tt,
            'amount' => $amount,
            'comment' => trim((string) ($it['comment'] ?? '')),
        ];
        $total_amount += $amount;
    }

    if ($clean_items === []) {
        throw new Exception('Add at least one row with Bank/Cash a/c and amount.');
    }

    // Top-bar Bank/Cash + Dr/Cr is the other contra side when the grid only has one side.
    $total_dr = 0.0;
    $total_cr = 0.0;
    foreach ($clean_items as $it) {
        if ($it['transaction_type'] === 'deposit') {
            $total_dr += (float) $it['amount'];
        } else {
            $total_cr += (float) $it['amount'];
        }
    }
    if ($header_bank !== '' && ($total_dr < 0.00001 || $total_cr < 0.00001)) {
        $header_already = false;
        foreach ($clean_items as $it) {
            if (strcasecmp($it['bank_cash_ac'], $header_bank) === 0 && $it['transaction_type'] === $header_tt) {
                $header_already = true;
                break;
            }
        }
        $header_is_dep = ($header_tt === 'deposit');
        $needs_header = ($header_is_dep && $total_dr < 0.00001) || (!$header_is_dep && $total_cr < 0.00001);
        $opposite_total = $header_is_dep ? $total_cr : $total_dr;
        if ($needs_header && !$header_already && $opposite_total > 0.00001) {
            $clean_items[] = [
                'bank_cash_ac' => $header_bank,
                'ref_no' => '',
                'ref_date' => $voucher_date,
                'transaction_type' => $header_tt,
                'amount' => $opposite_total,
                'comment' => $comment !== '' ? $comment : '',
            ];
            $total_amount += $opposite_total;
            if ($header_is_dep) {
                $total_dr += $opposite_total;
            } else {
                $total_cr += $opposite_total;
            }
        }
    }

    // Proper contra: total Deposit/Dr must equal total Withdrawal/Cr (both sides of the transfer).
    if ($total_dr < 0.00001 || $total_cr < 0.00001) {
        throw new Exception(
            'Contra needs both accounts: select the other Bank/Cash a/c and Dr/Cr in the top bar, '
            . 'or add a second row with the opposite Dr/Cr.'
        );
    }
    if (abs($total_dr - $total_cr) > 0.02) {
        throw new Exception(
            'Contra voucher is not balanced. Deposit/Dr (' . number_format($total_dr, 2, '.', '')
            . ') must equal Withdrawal/Cr (' . number_format($total_cr, 2, '.', '') . ').'
        );
    }
    // Store one-side transfer amount (not Dr+Cr sum) for Transaction Report
    $total_amount = max($total_dr, $total_cr);

    if ($voucher_id > 0) {
        $exists = getRecord("SELECT id FROM tbl_contra_vouchers WHERE id = $voucher_id");
        if (!$exists) {
            throw new Exception('Voucher not found.');
        }
        $upd = "UPDATE tbl_contra_vouchers SET
            voucher_date = '$voucher_date',
            total_amount = $total_amount,
            comment = " . ($comment !== '' ? "'$comment'" : 'NULL') . ",
            status = 'saved',
            updated_at = NOW()
            WHERE id = $voucher_id";
        if (!mysqli_query($conn, $upd)) {
            throw new Exception('Update failed: ' . mysqli_error($conn));
        }
        mysqli_query($conn, "DELETE FROM tbl_contra_voucher_items WHERE voucher_id = $voucher_id");
    } else {
        $dup = getRecord("SELECT id FROM tbl_contra_vouchers WHERE voucher_no = '$voucher_no'");
        if ($dup) {
            throw new Exception('Voucher number already exists.');
        }
        $created_by = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0);
        $branch_col = '';
        $branch_val = '';
        if ($has_cv_branch && $cv_branch > 0) {
            $branch_col = ', branch_id';
            $branch_val = ', ' . (int) $cv_branch;
        }
        $ins = "INSERT INTO tbl_contra_vouchers (voucher_no, voucher_date, total_amount, comment, status, created_by, created_at{$branch_col})
                VALUES ('$voucher_no', '$voucher_date', $total_amount, " . ($comment !== '' ? "'$comment'" : 'NULL') . ", 'saved', "
            . ($created_by > 0 ? $created_by : 'NULL') . ", NOW(){$branch_val})";
        if (!mysqli_query($conn, $ins)) {
            throw new Exception('Insert failed: ' . mysqli_error($conn));
        }
        $voucher_id = (int) mysqli_insert_id($conn);
    }

    foreach ($clean_items as $it) {
        $bank_cash_ac = esc($it['bank_cash_ac']);
        $ref_no = esc($it['ref_no']);
        $ref_date = $it['ref_date'] !== '' ? esc($it['ref_date']) : null;
        $transaction_type = esc($it['transaction_type']);
        $amount = (float) $it['amount'];
        $item_comment = esc($it['comment']);
        $ref_date_sql = $ref_date ? "'$ref_date'" : 'NULL';
        $ins_item = "INSERT INTO tbl_contra_voucher_items
            (voucher_id, bank_cash_ac, ref_no, ref_date, transaction_type, amount, comment, status)
            VALUES (
                $voucher_id,
                '$bank_cash_ac',
                " . ($ref_no !== '' ? "'$ref_no'" : 'NULL') . ",
                $ref_date_sql,
                '$transaction_type',
                $amount,
                " . ($item_comment !== '' ? "'$item_comment'" : 'NULL') . ",
                1
            )";
        if (!mysqli_query($conn, $ins_item)) {
            throw new Exception('Item insert failed: ' . mysqli_error($conn));
        }
    }

    // ----- Account ledger posting (proper double-entry on both accounts) -----
    auragold_ensure_customer_ledger_branch_column($conn);
    $ledger_has_branch_col = auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
    $ledger_branch_sql_val = ($ledger_has_branch_col && $cv_branch > 0)
        ? ', ' . (int) $cv_branch
        : ($ledger_has_branch_col ? ', NULL' : '');
    $ledger_br_scope = auragold_customer_ledger_branch_scope_sql($conn, $cv_branch);

    // Replace prior ledger rows for this voucher (edit-safe)
    mysqli_query($conn, "
        DELETE FROM tbl_customer_ledger
        WHERE transaction_type = 'contra_voucher' AND transaction_id = $voucher_id AND status = 1
    ");

    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : (isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : 0);
    $voucher_no_db = mysqli_real_escape_string($conn, $voucher_no);
    $voucher_date_db = mysqli_real_escape_string($conn, $voucher_date);
    $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
    $ledger_has_against = ($has_against && mysqli_num_rows($has_against) > 0);
    if ($has_against) {
        mysqli_free_result($has_against);
    }
    $has_gold_pure = false;
    $gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
    if ($gpc && mysqli_num_rows($gpc) > 0) {
        $has_gold_pure = true;
    }
    if ($gpc) {
        mysqli_free_result($gpc);
    }

    // Resolve / ensure every bank-cash ledger first so against labels use final names
    $resolved_items = [];
    foreach ($clean_items as $it) {
        $bank_raw = trim($it['bank_cash_ac']);
        $pt = (strcasecmp($bank_raw, 'Cash') === 0) ? 'cash' : 'bank';
        $ensured = auragold_ensure_payment_mode_ledger($conn, $bank_raw, $pt, $cv_branch);
        if (!$ensured['ok']) {
            throw new Exception('Could not create ledger "' . $bank_raw . '": ' . ($ensured['message'] ?? ''));
        }
        $ledger_name = $ensured['name'] !== '' ? $ensured['name'] : $bank_raw;
        $resolved_items[] = [
            'bank_cash_ac' => $bank_raw,
            'ledger_name' => $ledger_name,
            'ledger_id' => (int) ($ensured['id'] ?? 0),
            'ref_no' => $it['ref_no'],
            'transaction_type' => $it['transaction_type'],
            'amount' => (float) $it['amount'],
            'comment' => $it['comment'],
            'is_deposit' => ($it['transaction_type'] === 'deposit'),
        ];
    }

    /**
     * Build against_ledger for one contra line from opposite-side accounts.
     * Example: Cash Dr → "Bank(1000.00Cr)"; Bank Cr → "Cash(1000.00Dr)"
     *
     * @param list<array<string,mixed>> $all
     */
    $build_contra_against = static function (array $all, int $selfIdx): string {
        $self = $all[$selfIdx];
        $selfIsDep = !empty($self['is_deposit']);
        $parts = [];
        foreach ($all as $j => $other) {
            if ($j === $selfIdx) {
                continue;
            }
            $otherIsDep = !empty($other['is_deposit']);
            // Prefer opposite side only (true contra counterparties)
            if ($otherIsDep === $selfIsDep) {
                continue;
            }
            $side = $otherIsDep ? 'Dr' : 'Cr';
            $parts[] = $other['ledger_name'] . '(' . number_format((float) $other['amount'], 2, '.', '') . $side . ')';
        }
        // Fallback: if somehow no opposite (should not happen after balance check), list all others
        if ($parts === []) {
            foreach ($all as $j => $other) {
                if ($j === $selfIdx) {
                    continue;
                }
                $side = !empty($other['is_deposit']) ? 'Dr' : 'Cr';
                $parts[] = $other['ledger_name'] . '(' . number_format((float) $other['amount'], 2, '.', '') . $side . ')';
            }
        }
        return implode(', ', $parts);
    };

    foreach ($resolved_items as $idx => $it) {
        $amount = (float) $it['amount'];
        $is_deposit = !empty($it['is_deposit']);
        $ledger_name = $it['ledger_name'];
        $ledger_name_esc = esc($ledger_name);
        // Keep customer_id = 0 for Cash/Bank ledgers (same as payment-voucher mode posts)
        // so Account Ledger groups them with other cash/bank movements.
        $ledger_id = 0;

        $bal_cols = $has_gold_pure
            ? 'balance_amount, balance_gold, balance_silver, balance_gold_pure'
            : 'balance_amount, balance_gold, balance_silver';
        $last = getRecord("
            SELECT $bal_cols
            FROM tbl_customer_ledger
            WHERE customer_name = '$ledger_name_esc' AND status = 1
            $ledger_br_scope
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
        $prev = (float) ($last['balance_amount'] ?? 0);
        $prev_gold = (float) ($last['balance_gold'] ?? 0);
        $prev_silver = (float) ($last['balance_silver'] ?? 0);
        $prev_gold_pure = $has_gold_pure ? (float) ($last['balance_gold_pure'] ?? 0) : 0.0;

        $debit = $is_deposit ? $amount : 0.0;
        $credit = $is_deposit ? 0.0 : $amount;
        $new_bal = $prev + $debit - $credit;

        $against_display = $build_contra_against($resolved_items, $idx);
        $side_label = $is_deposit ? 'Deposit/Dr' : 'Withdrawal/Cr';
        $desc = 'Contra Voucher: ' . $voucher_no . ' (' . $side_label . ')';
        if ($against_display !== '') {
            $desc .= ' <-> ' . $against_display;
        }
        if ($it['comment'] !== '') {
            $desc .= ' - ' . $it['comment'];
        }
        $desc_esc = mysqli_real_escape_string($conn, $desc);
        $ref_sql = $it['ref_no'] !== ''
            ? "'" . mysqli_real_escape_string($conn, $it['ref_no']) . "'"
            : "'" . $voucher_no_db . "'";

        $against_cols = '';
        $against_vals = '';
        if ($ledger_has_against) {
            $against_cols = ', against_ledger, against_invoice_no';
            $ag_ledger = mysqli_real_escape_string(
                $conn,
                $against_display !== '' ? $against_display : ('Contra ' . $side_label)
            );
            $ag_inv = mysqli_real_escape_string($conn, $voucher_no);
            $against_vals = ", '$ag_ledger', '$ag_inv'";
        }

        $gold_pure_cols = $has_gold_pure ? ', debit_gold_pure, credit_gold_pure, balance_gold_pure' : '';
        $gold_pure_vals = $has_gold_pure
            ? ', 0, 0, ' . (float) $prev_gold_pure
            : '';

        $sql = "
            INSERT INTO tbl_customer_ledger (
                customer_id{$ledger_branch_sql_col}, customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                debit_gold, credit_gold, debit_silver, credit_silver,
                balance_amount, balance_gold, balance_silver
                {$gold_pure_cols},
                description, reference_no, status, created_by, created_at
                {$against_cols}
            ) VALUES (
                {$ledger_id}{$ledger_branch_sql_val},
                '$ledger_name_esc',
                'contra_voucher',
                $voucher_id,
                '$voucher_no_db',
                '$voucher_date_db',
                $debit,
                $credit,
                0, 0, 0, 0,
                $new_bal,
                $prev_gold,
                $prev_silver
                {$gold_pure_vals},
                '$desc_esc',
                $ref_sql,
                1,
                " . ($user_id > 0 ? $user_id : 'NULL') . ",
                NOW()
                {$against_vals}
            )
        ";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Ledger entry failed: ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);

    if (is_file(__DIR__ . '/../includes/auragold_notifications.php')) {
        require_once __DIR__ . '/../includes/auragold_notifications.php';
        if (function_exists('auragold_notify_document_saved')) {
            auragold_notify_document_saved($conn, [
                'label' => 'Contra Voucher',
                'verb' => 'saved',
                'number' => $voucher_no,
                'party' => '',
                'doc_date' => $voucher_date,
                'due_date' => '',
                'ref_id' => (int) $voucher_id,
            ]);
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Saved successfully.',
        'voucher_id' => $voucher_id,
        'voucher_no' => $voucher_no,
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
