<?php
/**
 * Header notifications: transactional alerts + daily due-date reminders.
 */

if (!function_exists('auragold_ensure_notifications_table')) {
    /**
     * Creates tbl_auragold_notifications on the operational branch DB when missing.
     */
    function auragold_ensure_notifications_table(mysqli $conn): void
    {
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return;
        }

        @mysqli_query(
            $conn,
            'CREATE TABLE IF NOT EXISTS tbl_auragold_notifications (
              id INT UNSIGNED NOT NULL AUTO_INCREMENT,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              title VARCHAR(255) NOT NULL,
              message TEXT NOT NULL,
              doc_kind VARCHAR(64) NULL DEFAULT NULL,
              ref_id INT NULL DEFAULT NULL,
              dedupe_key VARCHAR(191) NULL DEFAULT NULL,
              read_at DATETIME NULL DEFAULT NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uniq_dedupe_key (dedupe_key),
              KEY idx_created (created_at),
              KEY idx_unread (read_at, created_at DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $done[$key] = true;
    }
}

if (!function_exists('auragold_notify_normalize_date')) {
    /** Returns Y-m-d or ''. */
    function auragold_notify_normalize_date(?string $raw): string
    {
        if ($raw === null || trim($raw) === '') {
            return '';
        }
        $raw = trim(str_replace('/', '-', $raw));
        $t = strtotime($raw);
        if ($t === false) {
            return '';
        }
        return date('Y-m-d', $t);
    }
}

if (!function_exists('auragold_notify_format_display_date')) {
    function auragold_notify_format_display_date(string $ymd): string
    {
        $t = strtotime(str_replace('/', '-', $ymd));
        if ($t === false) {
            return $ymd;
        }
        return date('d/m/Y', $t);
    }
}

if (!function_exists('auragold_notify_spotlight_due_date')) {
    /** Matches user example 06-05-2026 (DD-MM-YYYY) = 2026-05-06. */
    function auragold_notify_spotlight_due_date(): string
    {
        return '2026-05-06';
    }
}

if (!function_exists('auragold_notify_append_message')) {
    /**
     * Inserts one notification row (no dedupe — each save emits a new row).
     *
     * @param array<string,mixed> $p
     */
    function auragold_notify_document_saved(mysqli $conn, array $p): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }

        $label = trim((string) ($p['label'] ?? ''));
        if ($label === '') {
            return;
        }

        $verb = strtolower(trim((string) ($p['verb'] ?? 'created')));
        if (!in_array($verb, ['created', 'updated'], true)) {
            $verb = 'created';
        }

        $number = trim((string) ($p['number'] ?? ''));
        $party = trim((string) ($p['party'] ?? ''));
        $docRaw = trim((string) ($p['doc_date'] ?? ''));
        $dueRaw = trim((string) ($p['due_date'] ?? ''));

        $docNorm = auragold_notify_normalize_date($docRaw);
        $dueNorm = auragold_notify_normalize_date($dueRaw);

        $dispDoc = $docNorm !== '' ? auragold_notify_format_display_date($docNorm) : ($docRaw !== '' ? $docRaw : 'N/A');
        $spot = auragold_notify_spotlight_due_date();

        $title = $label . ' ' . ($verb === 'updated' ? 'Updated' : 'Created');

        $parts = [];
        if ($number !== '') {
            $parts[] = 'A ' . strtolower($label) . ' ' . $number . ' has been ' . $verb . ' on ' . $dispDoc;
        } else {
            $parts[] = 'A ' . strtolower($label) . ' has been ' . $verb . ' on ' . $dispDoc;
        }
        if ($party !== '') {
            $parts[count($parts) - 1] .= ' for ' . $party . '.';
        } else {
            $parts[count($parts) - 1] .= '.';
        }

        if ($dueNorm !== '') {
            $dispDue = auragold_notify_format_display_date($dueNorm);
            $parts[] = 'The due date for this document is ' . $dispDue . '.';
            $today = date('Y-m-d');
            if ($dueNorm === $today) {
                $parts[] = 'Due date is today.';
            }
            if ($dueNorm === $spot) {
                $parts[] = 'This order uses the highlighted due date 06/05/2026.';
            }
        }

        $message = implode(' ', $parts);

        auragold_ensure_notifications_table($conn);

        $title_esc = mysqli_real_escape_string($conn, $title);
        $message_esc = mysqli_real_escape_string($conn, $message);
        $kind_esc = mysqli_real_escape_string($conn, strtolower(preg_replace('/[^a-z0-9]+/i', '_', $label)));
        $ref_id = isset($p['ref_id']) ? (int) $p['ref_id'] : 0;
        $ref_sql = $ref_id > 0 ? (string) $ref_id : 'NULL';

        @mysqli_query(
            $conn,
            "INSERT INTO tbl_auragold_notifications (title, message, doc_kind, ref_id, dedupe_key) VALUES ('$title_esc', '$message_esc', '$kind_esc', $ref_sql, NULL)"
        );
    }
}

if (!function_exists('auragold_notifications_seed_due_today')) {
    /**
     * Idempotent reminders: one row per source document per calendar day (dedupe_key).
     */
    function auragold_notifications_seed_due_today(mysqli $conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }

        static $doneRequest = [];
        $reqKey = spl_object_hash($conn);
        if (!empty($doneRequest[$reqKey])) {
            return;
        }

        $dbName = '';
        $dbRes = @mysqli_query($conn, 'SELECT DATABASE() AS d');
        if ($dbRes && ($dbRow = mysqli_fetch_assoc($dbRes))) {
            $dbName = (string) ($dbRow['d'] ?? '');
        }
        if ($dbRes) {
            mysqli_free_result($dbRes);
        }
        $today = date('Y-m-d');
        $cacheFile = __DIR__ . '/../cache/notif_seed_' . md5($dbName . '|' . $today) . '.flag';
        if (is_file($cacheFile) && (time() - (int) filemtime($cacheFile)) < 3600) {
            $doneRequest[$reqKey] = true;
            return;
        }

        auragold_ensure_notifications_table($conn);

        $today_disp = auragold_notify_format_display_date($today);

        $checks = [
            ['pfx' => 'si', 'table' => 'tbl_sale_invoices', 'label' => 'Sale Invoice', 'no' => 'invoice_no', 'party' => 'customer_name', 'dcol' => 'invoice_date'],
            ['pfx' => 'psi', 'table' => 'tbl_pos_sale_invoices', 'label' => 'POS Sale Invoice', 'no' => 'invoice_no', 'party' => 'customer_name', 'dcol' => 'invoice_date'],
            ['pfx' => 'so', 'table' => 'tbl_sale_orders', 'label' => 'Sale Order', 'no' => 'order_no', 'party' => 'customer_name', 'dcol' => 'order_date'],
            ['pfx' => 'sq', 'table' => 'tbl_sale_quotations', 'label' => 'Sale Quotation', 'no' => 'quotation_no', 'party' => 'customer_name', 'dcol' => 'quotation_date'],
            ['pfx' => 'sr', 'table' => 'tbl_sale_returns', 'label' => 'Sale Return', 'no' => 'return_no', 'party' => 'customer_name', 'dcol' => 'return_date'],
            ['pfx' => 'pi', 'table' => 'tbl_purchase_invoices', 'label' => 'Purchase Invoice', 'no' => 'invoice_no', 'party' => 'supplier_name', 'dcol' => 'invoice_date'],
            ['pfx' => 'pq', 'table' => 'tbl_purchase_quotations', 'label' => 'Purchase Quotation', 'no' => 'quotation_no', 'party' => 'supplier_name', 'dcol' => 'quotation_date'],
            ['pfx' => 'pr', 'table' => 'tbl_purchase_returns', 'label' => 'Purchase Return', 'no' => 'return_no', 'party' => 'supplier_name', 'dcol' => 'return_date'],
            ['pfx' => 'pv', 'table' => 'tbl_payment_vouchers', 'label' => 'Payment Voucher', 'no' => 'voucher_no', 'party' => 'customer_name', 'dcol' => 'voucher_date'],
            ['pfx' => 'rv', 'table' => 'tbl_receipt_vouchers', 'label' => 'Receipt Voucher', 'no' => 'voucher_no', 'party' => 'customer_name', 'dcol' => 'voucher_date'],
            ['pfx' => 'ap', 'table' => 'tbl_advance_payments', 'label' => 'Advance Payment', 'no' => 'voucher_no', 'party' => 'customer_name', 'dcol' => 'voucher_date'],
            ['pfx' => 'mi', 'table' => 'tbl_material_issues', 'label' => 'Material Issue', 'no' => 'material_issue_no', 'party' => 'customer_name', 'dcol' => 'order_date'],
            ['pfx' => 'mr', 'table' => 'tbl_material_receives', 'label' => 'Material Receive', 'no' => 'material_receive_no', 'party' => 'customer_name', 'dcol' => 'order_date'],
            ['pfx' => 'jwo', 'table' => 'tbl_jobwork_orders', 'label' => 'Jobwork Order', 'no' => 'jobwork_no', 'party' => 'customer_name', 'dcol' => 'order_date'],
            ['pfx' => 'oj', 'table' => 'tbl_old_jewelry_scrap_invoices', 'label' => 'Old Jewelry Scrap Invoice', 'no' => 'invoice_no', 'party' => 'customer_name', 'dcol' => 'invoice_date'],
        ];

        foreach ($checks as $c) {
            $tbl = $c['table'];
            $tQ = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tbl) . "'");
            if (!$tQ || mysqli_num_rows($tQ) === 0) {
                if ($tQ) {
                    mysqli_free_result($tQ);
                }
                continue;
            }
            mysqli_free_result($tQ);

            $du = @mysqli_query($conn, 'SHOW COLUMNS FROM `' . $tbl . "` LIKE 'due_date'");
            if (!$du || mysqli_num_rows($du) === 0) {
                if ($du) {
                    mysqli_free_result($du);
                }
                continue;
            }
            mysqli_free_result($du);

            $no = $c['no'];
            $party = $c['party'];
            $dcol = $c['dcol'];
            $sql = "SELECT id, `" . $no . "`, `" . $party . "`, `" . $dcol . "`, due_date FROM `" . $tbl
                . "` WHERE due_date IS NOT NULL AND DATE(due_date) = CURDATE() LIMIT 150";

            $rows = @getList($sql);
            if (!is_array($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $num = trim((string) ($row[$no] ?? ''));
                $pty = trim((string) ($row[$party] ?? ''));
                $dedupe = 'due_today:' . $c['pfx'] . ':' . $id . ':' . $today;

                $title = 'Due date today';
                $msg = $c['label'] . ($num !== '' ? ' ' . $num : '') . ' for ' . ($pty !== '' ? $pty : '—')
                    . ' has due date ' . $today_disp . '.';

                $title_esc = mysqli_real_escape_string($conn, $title);
                $msg_esc = mysqli_real_escape_string($conn, $msg);
                $ded_esc = mysqli_real_escape_string($conn, $dedupe);
                $kind_esc = mysqli_real_escape_string($conn, 'due_today_' . $c['pfx']);

                @mysqli_query(
                    $conn,
                    "INSERT IGNORE INTO tbl_auragold_notifications (title, message, doc_kind, ref_id, dedupe_key) VALUES ('$title_esc', '$msg_esc', '$kind_esc', $id, '$ded_esc')"
                );
            }
        }

        $doneRequest[$reqKey] = true;
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        @touch($cacheFile);
    }
}
