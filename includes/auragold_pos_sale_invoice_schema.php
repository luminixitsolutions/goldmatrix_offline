<?php

if (!function_exists('auragold_pos_sale_invoice_table_exists')) {
    function auragold_pos_sale_invoice_table_exists(mysqli $conn, string $table): bool
    {
        $t = mysqli_real_escape_string($conn, $table);
        $r = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }

        return $ok;
    }
}

if (!function_exists('auragold_pos_sale_invoice_fix_child_fk')) {
    /**
     * After CREATE TABLE … LIKE, child FKs may still reference tbl_sale_invoices; repoint to tbl_pos_sale_invoices.
     */
    function auragold_pos_sale_invoice_fix_child_fk(mysqli $conn, string $childTable, string $parentTable): void
    {
        $dbR = @mysqli_query($conn, 'SELECT DATABASE() AS d');
        if (!$dbR || !($row = mysqli_fetch_assoc($dbR))) {
            if ($dbR) {
                mysqli_free_result($dbR);
            }

            return;
        }
        mysqli_free_result($dbR);
        $dbEsc = mysqli_real_escape_string($conn, (string) ($row['d'] ?? ''));
        if ($dbEsc === '') {
            return;
        }
        $childEsc = mysqli_real_escape_string($conn, $childTable);
        $parentEsc = mysqli_real_escape_string($conn, $parentTable);

        $res = @mysqli_query(
            $conn,
            "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = '$dbEsc' AND TABLE_NAME = '$childEsc'"
        );
        $constraints = [];
        if ($res) {
            while ($rw = mysqli_fetch_assoc($res)) {
                $cn = (string) ($rw['CONSTRAINT_NAME'] ?? '');
                $ref = (string) ($rw['REFERENCED_TABLE_NAME'] ?? '');
                if ($cn !== '') {
                    $constraints[$cn] = $ref;
                }
            }
            mysqli_free_result($res);
        }

        foreach ($constraints as $constraintName => $refTable) {
            if ($refTable === $parentTable) {
                continue;
            }
            $cnEsc = mysqli_real_escape_string($conn, $constraintName);
            @mysqli_query($conn, "ALTER TABLE `$childEsc` DROP FOREIGN KEY `$cnEsc`");
        }

        $hasParentFk = false;
        $res2 = @mysqli_query(
            $conn,
            "SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = '$dbEsc' AND TABLE_NAME = '$childEsc' AND REFERENCED_TABLE_NAME = '$parentEsc' LIMIT 1"
        );
        if ($res2 && mysqli_num_rows($res2) > 0) {
            $hasParentFk = true;
        }
        if ($res2) {
            mysqli_free_result($res2);
        }
        if ($hasParentFk) {
            return;
        }

        $short = ($childTable === 'tbl_pos_sale_invoice_items') ? 'fk_psi_invoice' : 'fk_psp_invoice';
        @mysqli_query(
            $conn,
            "ALTER TABLE `$childEsc` ADD CONSTRAINT `$short` FOREIGN KEY (`invoice_id`) REFERENCES `$parentEsc` (`id`) ON DELETE CASCADE"
        );
    }
}

if (!function_exists('auragold_ensure_pos_sale_invoice_tables')) {
    function auragold_ensure_pos_sale_invoice_tables(mysqli $conn): void
    {
        if (!auragold_pos_sale_invoice_table_exists($conn, 'tbl_sale_invoices')) {
            return;
        }
        if (!auragold_pos_sale_invoice_table_exists($conn, 'tbl_pos_sale_invoices')) {
            @mysqli_query($conn, 'CREATE TABLE IF NOT EXISTS tbl_pos_sale_invoices LIKE tbl_sale_invoices');
        }
        if (!auragold_pos_sale_invoice_table_exists($conn, 'tbl_pos_sale_invoice_items')) {
            @mysqli_query($conn, 'CREATE TABLE IF NOT EXISTS tbl_pos_sale_invoice_items LIKE tbl_sale_invoice_items');
        }
        if (!auragold_pos_sale_invoice_table_exists($conn, 'tbl_pos_sale_invoice_payments')) {
            @mysqli_query($conn, 'CREATE TABLE IF NOT EXISTS tbl_pos_sale_invoice_payments LIKE tbl_sale_invoice_payments');
        }
        auragold_pos_sale_invoice_fix_child_fk($conn, 'tbl_pos_sale_invoice_items', 'tbl_pos_sale_invoices');
        auragold_pos_sale_invoice_fix_child_fk($conn, 'tbl_pos_sale_invoice_payments', 'tbl_pos_sale_invoices');
    }
}
