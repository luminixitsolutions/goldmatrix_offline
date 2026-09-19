<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold_ensure_payment_mode_ledger.php';
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

// Create journal voucher tables if they do not exist
$t1 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_journal_vouchers'");
if (!$t1 || mysqli_num_rows($t1) === 0) {
    $create_main = "CREATE TABLE IF NOT EXISTS `tbl_journal_vouchers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `voucher_no` varchar(50) NOT NULL,
        `voucher_date` date NOT NULL,
        `comment` text DEFAULT NULL,
        `credit_wt` decimal(15,4) DEFAULT 0.0000,
        `debit_wt` decimal(15,4) DEFAULT 0.0000,
        `debit_total` decimal(15,2) DEFAULT 0.00,
        `credit_total` decimal(15,2) DEFAULT 0.00,
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
        echo json_encode(['status' => 'error', 'message' => 'Could not create tbl_journal_vouchers: ' . mysqli_error($conn)]);
        exit;
    }
}
$t2 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_journal_voucher_items'");
if (!$t2 || mysqli_num_rows($t2) === 0) {
    $create_items = "CREATE TABLE IF NOT EXISTS `tbl_journal_voucher_items` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `voucher_id` int(11) NOT NULL,
        `branch_id` int(11) DEFAULT NULL,
        `branch_name` varchar(100) DEFAULT NULL,
        `account_ledger` varchar(200) NOT NULL,
        `cr_dr` varchar(10) NOT NULL DEFAULT 'Dr',
        `against` varchar(200) DEFAULT NULL,
        `ref_no` varchar(100) DEFAULT NULL,
        `ref_date` date DEFAULT NULL,
        `amount` decimal(15,2) DEFAULT 0.00,
        `metal` varchar(50) DEFAULT NULL,
        `purity_wt` decimal(15,4) DEFAULT 0.0000,
        `status` tinyint(1) DEFAULT 1,
        `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `voucher_id` (`voucher_id`),
        KEY `branch_id` (`branch_id`),
        CONSTRAINT `fk_journal_voucher_items_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `tbl_journal_vouchers` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    if (!mysqli_query($conn, $create_items)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not create tbl_journal_voucher_items: ' . mysqli_error($conn)]);
        exit;
    }
}

$has_jv_branch = function_exists('auragold_ensure_table_branch_id_column')
    ? auragold_ensure_table_branch_id_column($conn, 'tbl_journal_vouchers')
    : false;
$hdr_branch = function_exists('auragold_transaction_header_branch_id')
    ? (int) auragold_transaction_header_branch_id()
    : 0;
$eff_branch = function_exists('auragold_effective_branch_id')
    ? (int) auragold_effective_branch_id()
    : 0;
$jv_branch_default = $hdr_branch > 0 ? $hdr_branch : $eff_branch;

mysqli_begin_transaction($conn);

try {
    $voucher_id = isset($_POST['voucher_id']) ? (int) $_POST['voucher_id'] : 0;
    $voucher_no = esc($_POST['voucher_no'] ?? '');
    $voucher_date = esc($_POST['voucher_date'] ?? date('Y-m-d'));
    $comment = esc($_POST['comment'] ?? '');
    $credit_wt = (float) ($_POST['credit_wt'] ?? 0);
    $debit_wt = (float) ($_POST['debit_wt'] ?? 0);
    $debit_total = (float) ($_POST['debit_total'] ?? 0);
    $credit_total = (float) ($_POST['credit_total'] ?? 0);
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
    $sum_dr = 0.0;
    $sum_cr = 0.0;
    $sum_dr_wt = 0.0;
    $sum_cr_wt = 0.0;
    foreach ($items as $it) {
        if (!is_array($it)) {
            continue;
        }
        $ledger = trim((string) ($it['account_ledger'] ?? ''));
        $amount = (float) ($it['amount'] ?? 0);
        $purity_wt = (float) ($it['purity_wt'] ?? 0);
        $cr_dr = strtoupper(trim((string) ($it['cr_dr'] ?? 'Dr')));
        if ($cr_dr !== 'CR' && $cr_dr !== 'DR') {
            $cr_dr = 'DR';
        }
        $cr_dr = ($cr_dr === 'CR') ? 'Cr' : 'Dr';
        if ($ledger === '' || ($amount <= 0 && $purity_wt <= 0)) {
            continue;
        }
        $clean_items[] = [
            'branch_id' => (int) ($it['branch_id'] ?? 0),
            'branch_name' => trim((string) ($it['branch_name'] ?? '')),
            'account_ledger' => $ledger,
            'cr_dr' => $cr_dr,
            'against' => trim((string) ($it['against'] ?? '')),
            'ref_no' => trim((string) ($it['ref_no'] ?? '')),
            'ref_date' => trim((string) ($it['ref_date'] ?? '')),
            'amount' => $amount,
            'metal' => trim((string) ($it['metal'] ?? '')),
            'purity_wt' => $purity_wt,
        ];
        if ($cr_dr === 'Dr') {
            $sum_dr += $amount;
            $sum_dr_wt += $purity_wt;
        } else {
            $sum_cr += $amount;
            $sum_cr_wt += $purity_wt;
        }
    }

    if ($clean_items === []) {
        throw new Exception('Add at least one row with Account Ledger and Amount (or metal weight).');
    }

    // Prefer computed totals from lines
    if ($sum_dr > 0 || $sum_cr > 0) {
        $debit_total = $sum_dr;
        $credit_total = $sum_cr;
    }
    if ($sum_dr_wt > 0 || $sum_cr_wt > 0) {
        $debit_wt = $sum_dr_wt;
        $credit_wt = $sum_cr_wt;
    }
    if ($sum_dr < 0.00001 || $sum_cr < 0.00001) {
        // Allow pure metal journals (weights only) when amount sides empty but weights balance
        if (!(($sum_dr_wt > 0.00001 || $sum_cr_wt > 0.00001) && abs($sum_dr_wt - $sum_cr_wt) < 0.0001 && $sum_dr < 0.00001 && $sum_cr < 0.00001)) {
            throw new Exception('Journal voucher needs both sides: at least one Debit (Dr) and one Credit (Cr) row.');
        }
    }
    if (abs($debit_total - $credit_total) > 0.01) {
        throw new Exception('Debit Total and Credit Total must match.');
    }

    if ($voucher_id > 0) {
        $exists = getRecord("SELECT id FROM tbl_journal_vouchers WHERE id = $voucher_id");
        if (!$exists) {
            throw new Exception('Voucher not found.');
        }
        $upd = "UPDATE tbl_journal_vouchers SET
            voucher_date = '$voucher_date',
            comment = " . ($comment !== '' ? "'$comment'" : 'NULL') . ",
            credit_wt = $credit_wt,
            debit_wt = $debit_wt,
            debit_total = $debit_total,
            credit_total = $credit_total,
            status = 'saved',
            updated_at = NOW()
            WHERE id = $voucher_id";
        if (!mysqli_query($conn, $upd)) {
            throw new Exception('Update failed: ' . mysqli_error($conn));
        }
        mysqli_query($conn, "DELETE FROM tbl_journal_voucher_items WHERE voucher_id = $voucher_id");
    } else {
        $dup = getRecord("SELECT id FROM tbl_journal_vouchers WHERE voucher_no = '$voucher_no'");
        if ($dup) {
            throw new Exception('Voucher number already exists.');
        }
        $created_by = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0);
        $branch_col = '';
        $branch_val = '';
        if ($has_jv_branch && $jv_branch_default > 0) {
            $branch_col = ', branch_id';
            $branch_val = ', ' . (int) $jv_branch_default;
        }
        $ins = "INSERT INTO tbl_journal_vouchers
            (voucher_no, voucher_date, comment, credit_wt, debit_wt, debit_total, credit_total, status, created_by, created_at{$branch_col})
            VALUES (
                '$voucher_no',
                '$voucher_date',
                " . ($comment !== '' ? "'$comment'" : 'NULL') . ",
                $credit_wt, $debit_wt, $debit_total, $credit_total,
                'saved',
                " . ($created_by > 0 ? $created_by : 'NULL') . ",
                NOW(){$branch_val}
            )";
        if (!mysqli_query($conn, $ins)) {
            throw new Exception('Insert failed: ' . mysqli_error($conn));
        }
        $voucher_id = (int) mysqli_insert_id($conn);
    }

    foreach ($clean_items as $it) {
        $branch_id = (int) $it['branch_id'];
        $branch_id_sql = ($branch_id > 0) ? $branch_id : 'NULL';
        $branch_name = esc($it['branch_name']);
        $account_ledger = esc($it['account_ledger']);
        $cr_dr = $it['cr_dr'];
        $against = esc($it['against']);
        $ref_no = esc($it['ref_no']);
        $ref_date = $it['ref_date'] !== '' ? "'" . esc($it['ref_date']) . "'" : 'NULL';
        $amount = (float) $it['amount'];
        $metal = esc($it['metal']);
        $purity_wt = (float) $it['purity_wt'];

        $ins_item = "INSERT INTO tbl_journal_voucher_items
            (voucher_id, branch_id, branch_name, account_ledger, cr_dr, against, ref_no, ref_date, amount, metal, purity_wt, status)
            VALUES (
                $voucher_id,
                $branch_id_sql,
                " . ($branch_name !== '' ? "'$branch_name'" : 'NULL') . ",
                '$account_ledger',
                '$cr_dr',
                " . ($against !== '' ? "'$against'" : 'NULL') . ",
                " . ($ref_no !== '' ? "'$ref_no'" : 'NULL') . ",
                $ref_date,
                $amount,
                " . ($metal !== '' ? "'$metal'" : 'NULL') . ",
                $purity_wt,
                1
            )";
        if (!mysqli_query($conn, $ins_item)) {
            throw new Exception('Item insert failed: ' . mysqli_error($conn));
        }
    }

    // ----- Account ledger posting (proper Dr/Cr on each account + opposite Against) -----
    auragold_ensure_customer_ledger_branch_column($conn);
    $ledger_has_branch_col = auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $has_gold_pure = false;
    $gpc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'debit_gold_pure'");
    if ($gpc && mysqli_num_rows($gpc) > 0) {
        $has_gold_pure = true;
    }
    if ($gpc) {
        mysqli_free_result($gpc);
    }
    $has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
    $ledger_has_against = ($has_against && mysqli_num_rows($has_against) > 0);
    if ($has_against) {
        mysqli_free_result($has_against);
    }

    mysqli_query($conn, "
        DELETE FROM tbl_customer_ledger
        WHERE transaction_type = 'journal_voucher' AND transaction_id = $voucher_id AND status = 1
    ");

    $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : (isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : 0);
    $voucher_no_db = mysqli_real_escape_string($conn, $voucher_no);
    $voucher_date_db = mysqli_real_escape_string($conn, $voucher_date);

    // Resolve ledger names first so Against labels use final names
    $resolved_items = [];
    foreach ($clean_items as $it) {
        $ledger_name = trim($it['account_ledger']);
        $line_branch = (int) $it['branch_id'] > 0 ? (int) $it['branch_id'] : $jv_branch_default;
        $name_esc = esc($ledger_name);
        $existing = getRecord("SELECT id, name FROM tbl_customers WHERE TRIM(name) = '$name_esc' AND status = 1 LIMIT 1");
        $ledger_id = 0;
        if (is_array($existing) && (int) ($existing['id'] ?? 0) > 0) {
            $ledger_id = (int) $existing['id'];
            $ledger_name = (string) ($existing['name'] ?? $ledger_name);
        } else {
            $pt = (strcasecmp($ledger_name, 'Cash') === 0) ? 'cash' : 'bank';
            $ensured = auragold_ensure_payment_mode_ledger($conn, $ledger_name, $pt, $line_branch);
            if (!$ensured['ok']) {
                throw new Exception('Could not create ledger "' . $ledger_name . '": ' . ($ensured['message'] ?? ''));
            }
            $ledger_id = (int) ($ensured['id'] ?? 0);
            $ledger_name = $ensured['name'] !== '' ? $ensured['name'] : $ledger_name;
        }
        // Cash / Bank-style ledgers: post with customer_id = 0 (same as payment/contra mode posts)
        $is_cash_bank = (bool) preg_match('/^(cash|bank|boi)\b/i', $ledger_name)
            || strcasecmp($ledger_name, 'Cash') === 0
            || strcasecmp($ledger_name, 'Bank') === 0;
        $resolved_items[] = array_merge($it, [
            'ledger_name' => $ledger_name,
            'ledger_id' => $is_cash_bank ? 0 : $ledger_id,
            'line_branch' => $line_branch,
            'is_dr' => ($it['cr_dr'] === 'Dr'),
        ]);
    }

    /**
     * Opposite-side Against string, e.g. Cash Dr → "Sales Account(1000.00Cr)"
     *
     * @param list<array<string,mixed>> $all
     */
    $build_jv_against = static function (array $all, int $selfIdx): string {
        $selfIsDr = !empty($all[$selfIdx]['is_dr']);
        $parts = [];
        foreach ($all as $j => $other) {
            if ($j === $selfIdx) {
                continue;
            }
            $otherIsDr = !empty($other['is_dr']);
            if ($otherIsDr === $selfIsDr) {
                continue;
            }
            $amt = (float) ($other['amount'] ?? 0);
            $wt = (float) ($other['purity_wt'] ?? 0);
            $side = $otherIsDr ? 'Dr' : 'Cr';
            $labelAmt = $amt > 0.00001 ? number_format($amt, 2, '.', '') : number_format($wt, 3, '.', '');
            $parts[] = $other['ledger_name'] . '(' . $labelAmt . $side . ')';
        }
        if ($parts === []) {
            foreach ($all as $j => $other) {
                if ($j === $selfIdx) {
                    continue;
                }
                $otherIsDr = !empty($other['is_dr']);
                $amt = (float) ($other['amount'] ?? 0);
                $wt = (float) ($other['purity_wt'] ?? 0);
                $side = $otherIsDr ? 'Dr' : 'Cr';
                $labelAmt = $amt > 0.00001 ? number_format($amt, 2, '.', '') : number_format($wt, 3, '.', '');
                $parts[] = $other['ledger_name'] . '(' . $labelAmt . $side . ')';
            }
        }
        return implode(', ', $parts);
    };

    foreach ($resolved_items as $idx => $it) {
        $ledger_name = $it['ledger_name'];
        $ledger_id = (int) $it['ledger_id'];
        $amount = (float) $it['amount'];
        $purity_wt = (float) $it['purity_wt'];
        $is_dr = !empty($it['is_dr']);
        $line_branch = (int) $it['line_branch'];
        $ledger_br_scope = auragold_customer_ledger_branch_scope_sql($conn, $line_branch);
        $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
        $ledger_branch_sql_val = ($ledger_has_branch_col && $line_branch > 0)
            ? ', ' . (int) $line_branch
            : ($ledger_has_branch_col ? ', NULL' : '');

        $ledger_name_esc = esc($ledger_name);

        $metal_lc = strtolower(trim($it['metal']));
        $line_gold = 0.0;
        $line_silver = 0.0;
        if ($purity_wt > 0) {
            if ($metal_lc === '' || strpos($metal_lc, 'gold') !== false) {
                $line_gold = $purity_wt;
            } elseif (strpos($metal_lc, 'silver') !== false) {
                $line_silver = $purity_wt;
            } else {
                $line_gold = $purity_wt;
            }
        }

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
        $prev_amt = (float) ($last['balance_amount'] ?? 0);
        $prev_gold = (float) ($last['balance_gold'] ?? 0);
        $prev_silver = (float) ($last['balance_silver'] ?? 0);
        $prev_gold_pure = $has_gold_pure ? (float) ($last['balance_gold_pure'] ?? 0) : 0.0;

        $debit_amt = $is_dr ? $amount : 0.0;
        $credit_amt = $is_dr ? 0.0 : $amount;
        $debit_gold = $is_dr ? $line_gold : 0.0;
        $credit_gold = $is_dr ? 0.0 : $line_gold;
        $debit_silver = $is_dr ? $line_silver : 0.0;
        $credit_silver = $is_dr ? 0.0 : $line_silver;
        $new_amt = $prev_amt + $debit_amt - $credit_amt;
        $new_gold = $prev_gold + $debit_gold - $credit_gold;
        $new_silver = $prev_silver + $debit_silver - $credit_silver;
        $new_gold_pure = $prev_gold_pure + $debit_gold - $credit_gold;

        $against_display = $build_jv_against($resolved_items, $idx);
        $desc = 'Journal Voucher: ' . $voucher_no . ' (' . $it['cr_dr'] . ')';
        if ($against_display !== '') {
            $desc .= ' <-> ' . $against_display;
        }
        if ($it['against'] !== '') {
            $desc .= ' Ref ' . $it['against'];
        }
        if ($line_gold > 0 || $line_silver > 0) {
            $desc .= ' (Hedging)';
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
                $against_display !== '' ? $against_display : ('Journal ' . $it['cr_dr'])
            );
            // Prefer document against (invoice/voucher no) for Against Invoice column; else JV no
            $ag_inv_raw = $it['against'] !== '' ? $it['against'] : ($it['ref_no'] !== '' ? $it['ref_no'] : $voucher_no);
            $ag_inv = mysqli_real_escape_string($conn, $ag_inv_raw);
            $against_vals = ", '$ag_ledger', '$ag_inv'";
        }

        $gold_pure_cols = $has_gold_pure ? ', debit_gold_pure, credit_gold_pure, balance_gold_pure' : '';
        $gold_pure_vals = $has_gold_pure
            ? ', ' . (float) $debit_gold . ', ' . (float) $credit_gold . ', ' . (float) $new_gold_pure
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
                'journal_voucher',
                $voucher_id,
                '$voucher_no_db',
                '$voucher_date_db',
                $debit_amt,
                $credit_amt,
                " . (float) $debit_gold . ",
                " . (float) $credit_gold . ",
                " . (float) $debit_silver . ",
                " . (float) $credit_silver . ",
                $new_amt,
                $new_gold,
                $new_silver
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
            throw new Exception('Ledger entry failed for "' . $ledger_name . '": ' . mysqli_error($conn));
        }
    }

    mysqli_commit($conn);

    if (is_file(__DIR__ . '/../includes/auragold_notifications.php')) {
        require_once __DIR__ . '/../includes/auragold_notifications.php';
        if (function_exists('auragold_notify_document_saved')) {
            auragold_notify_document_saved($conn, [
                'label' => 'Journal Voucher',
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
