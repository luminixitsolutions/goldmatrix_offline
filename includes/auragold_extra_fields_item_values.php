<?php

/**
 * Persist product-modal extra field values on voucher line items (extra_fields_json column).
 */

if (!function_exists('auragold_extra_fields_voucher_item_tables')) {
    function auragold_extra_fields_voucher_item_tables(): array
    {
        return [
            'tbl_stock_journal',
            'tbl_sale_invoice_items',
            'tbl_pos_sale_invoice_items',
            'tbl_sale_quotation_items',
            'tbl_sale_return_items',
            'tbl_purchase_invoice_items',
            'tbl_purchase_quotation_items',
            'tbl_purchase_return_items',
            'tbl_sale_order_items',
            'tbl_jobwork_order_items',
            'tbl_material_issue_items',
            'tbl_material_receive_items',
            'tbl_consignment_in_items',
            'tbl_consignment_out_items',
            'tbl_old_jewelry_scrap_invoice_items',
        ];
    }
}

if (!function_exists('auragold_ensure_extra_fields_json_column')) {
    function auragold_ensure_extra_fields_json_column($conn, string $table): bool
    {
        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }

        if (auragold_tbl_has_column($conn, $table, 'extra_fields_json')) {
            return true;
        }

        $tEsc = mysqli_real_escape_string($conn, $table);
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE '$tEsc'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return false;
        }
        mysqli_free_result($chk);

        $afterCandidates = ['net_amt_with_tax', 'net_amt_wt', 'amount', 'rate'];
        foreach ($afterCandidates as $afterCol) {
            if (!auragold_tbl_has_column($conn, $table, $afterCol)) {
                continue;
            }
            $afterEsc = mysqli_real_escape_string($conn, $afterCol);
            @mysqli_query(
                $conn,
                "ALTER TABLE `$tEsc` ADD COLUMN extra_fields_json TEXT NULL DEFAULT NULL COMMENT 'Extra field values JSON map field_id=>value' AFTER `$afterEsc`"
            );
            if (auragold_tbl_has_column($conn, $table, 'extra_fields_json')) {
                return true;
            }
        }

        @mysqli_query(
            $conn,
            "ALTER TABLE `$tEsc` ADD COLUMN extra_fields_json TEXT NULL DEFAULT NULL COMMENT 'Extra field values JSON map field_id=>value'"
        );

        return auragold_tbl_has_column($conn, $table, 'extra_fields_json');
    }
}

if (!function_exists('auragold_extra_fields_json_from_item')) {
    function auragold_extra_fields_json_from_item($item): ?string
    {
        if (!is_array($item)) {
            return null;
        }

        if (isset($item['extra_fields_json']) && is_string($item['extra_fields_json']) && trim($item['extra_fields_json']) !== '') {
            $trim = trim($item['extra_fields_json']);
            json_decode($trim, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $trim;
            }
        }

        if (!isset($item['extra_fields']) || !is_array($item['extra_fields'])) {
            return null;
        }

        $clean = [];
        foreach ($item['extra_fields'] as $fieldId => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $clean[(string) $fieldId] = (string) $value;
        }

        if ($clean === []) {
            return null;
        }

        $json = json_encode($clean, JSON_UNESCAPED_UNICODE);
        return ($json !== false && $json !== '[]') ? $json : null;
    }
}

if (!function_exists('auragold_extra_fields_item_insert_sql_parts')) {
    function auragold_extra_fields_item_insert_sql_parts($conn, string $table, array $item): array
    {
        static $hasColumn = [];

        if (!isset($hasColumn[$table])) {
            $hasColumn[$table] = auragold_ensure_extra_fields_json_column($conn, $table);
        }

        if (!$hasColumn[$table]) {
            return ['columns' => '', 'values' => ''];
        }

        $json = auragold_extra_fields_json_from_item($item);
        $valSql = $json !== null
            ? "'" . mysqli_real_escape_string($conn, $json) . "'"
            : 'NULL';

        return [
            'columns' => ', extra_fields_json',
            'values' => ', ' . $valSql,
        ];
    }
}

if (!function_exists('auragold_extra_fields_parse_item_json')) {
    function auragold_extra_fields_parse_item_json(array $item): array
    {
        if (isset($item['extra_fields']) && is_array($item['extra_fields'])) {
            return $item['extra_fields'];
        }

        $raw = $item['extra_fields_json'] ?? '';
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
