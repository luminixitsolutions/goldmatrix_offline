<?php

/**
 * Parse POST pending diamond/stone allocation queues (same keys as sale-order save)
 * and apply inside an open DB transaction.
 */

if (!function_exists('auragold_voucher_parse_pending_diamond_lines_from_post')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_voucher_parse_pending_diamond_lines_from_post(): array
    {
        $pending_raw = $_POST['pending_diamond_allocations'] ?? '[]';
        if (is_string($pending_raw)) {
            $pending_in = json_decode($pending_raw, true);
        } elseif (is_array($pending_raw)) {
            $pending_in = $pending_raw;
        } else {
            $pending_in = [];
        }
        if (!is_array($pending_in)) {
            $pending_in = [];
        }
        $diamond_lines = [];
        foreach ($pending_in as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $source_issue_id = (int) ($ln['source_issue_id'] ?? $ln['issue_line_id'] ?? $ln['issue_id'] ?? 0);
            $sid = (int) ($ln['stock_id'] ?? 0);
            $qty = isset($ln['allocate_qty']) ? (float) $ln['allocate_qty'] : (isset($ln['qty']) ? (float) $ln['qty'] : 0);
            $wt = isset($ln['allocate_weight']) ? (float) $ln['allocate_weight'] : (isset($ln['weight']) ? (float) $ln['weight'] : 0);
            if ($source_issue_id < 1 && $sid < 1) {
                continue;
            }
            if ($source_issue_id < 1 && ($qty <= 0 && $wt <= 0)) {
                continue;
            }
            if ($source_issue_id > 0 && $wt <= 0.0000001) {
                continue;
            }
            $line = [
                'stock_id' => $sid,
                'barcode' => isset($ln['barcode']) ? trim((string) $ln['barcode']) : '',
                'qty' => $qty,
                'weight' => $wt,
                'product_name' => isset($ln['product_name']) ? trim((string) $ln['product_name']) : '',
                'diamond_category' => isset($ln['diamond_category']) ? trim((string) $ln['diamond_category']) : '',
                'product_characteristic_id' => (int) ($ln['product_characteristic_id'] ?? 0),
                'source_stock_id' => (int) ($ln['source_stock_id'] ?? 0),
            ];
            if ($source_issue_id > 0) {
                $line['source_issue_id'] = $source_issue_id;
            }
            $diamond_lines[] = $line;
        }

        return $diamond_lines;
    }
}

if (!function_exists('auragold_voucher_parse_pending_stone_lines_from_post')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_voucher_parse_pending_stone_lines_from_post(): array
    {
        $pending_raw = $_POST['pending_stone_allocations'] ?? '[]';
        if (is_string($pending_raw)) {
            $pending_in = json_decode($pending_raw, true);
        } elseif (is_array($pending_raw)) {
            $pending_in = $pending_raw;
        } else {
            $pending_in = [];
        }
        if (!is_array($pending_in)) {
            $pending_in = [];
        }
        $stone_lines = [];
        foreach ($pending_in as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $source_issue_id = (int) ($ln['source_issue_id'] ?? $ln['issue_line_id'] ?? $ln['issue_id'] ?? 0);
            $sid = (int) ($ln['stock_id'] ?? 0);
            $qty = isset($ln['allocate_qty']) ? (float) $ln['allocate_qty'] : (isset($ln['qty']) ? (float) $ln['qty'] : 0);
            $wt = isset($ln['allocate_weight']) ? (float) $ln['allocate_weight'] : (isset($ln['weight']) ? (float) $ln['weight'] : 0);
            if ($source_issue_id < 1 && $sid < 1) {
                continue;
            }
            if ($source_issue_id < 1 && ($qty <= 0 && $wt <= 0)) {
                continue;
            }
            if ($source_issue_id > 0 && $wt <= 0.0000001) {
                continue;
            }
            $line = [
                'stock_id' => $sid,
                'barcode' => isset($ln['barcode']) ? trim((string) $ln['barcode']) : '',
                'qty' => $qty,
                'weight' => $wt,
                'product_name' => isset($ln['product_name']) ? trim((string) $ln['product_name']) : '',
                'stone_category' => isset($ln['stone_category']) ? trim((string) $ln['stone_category']) : '',
                'product_characteristic_id' => (int) ($ln['product_characteristic_id'] ?? 0),
                'source_stock_id' => (int) ($ln['source_stock_id'] ?? 0),
            ];
            if ($source_issue_id > 0) {
                $line['source_issue_id'] = $source_issue_id;
            }
            $stone_lines[] = $line;
        }

        return $stone_lines;
    }
}

if (!function_exists('auragold_voucher_apply_pending_diamond_stone_from_post')) {
    /**
     * @throws Exception on allocation failure
     */
    function auragold_voucher_apply_pending_diamond_stone_from_post(
        mysqli $conn,
        string $voucher_kind,
        int $voucher_id,
        string $ref_no,
        string $ref_date_ymd
    ): void {
        if ($voucher_id < 1) {
            return;
        }
        require_once __DIR__ . '/auragold_voucher_diamond_stock.php';
        require_once __DIR__ . '/auragold_voucher_stone_stock.php';

        $diamond_lines = auragold_voucher_parse_pending_diamond_lines_from_post();
        $stone_lines = auragold_voucher_parse_pending_stone_lines_from_post();

        $tx_ok = true;
        $tx_err = '';
        if ($diamond_lines !== []) {
            auragold_voucher_apply_diamond_allocations(
                $conn,
                $voucher_kind,
                $voucher_id,
                $diamond_lines,
                $ref_no,
                $ref_date_ymd,
                $tx_ok,
                $tx_err
            );
            if (!$tx_ok) {
                throw new Exception($tx_err !== '' ? $tx_err : 'Diamond stock allocation failed.');
            }
        }
        if ($stone_lines !== []) {
            auragold_voucher_apply_stone_allocations(
                $conn,
                $voucher_kind,
                $voucher_id,
                $stone_lines,
                $ref_no,
                $ref_date_ymd,
                $tx_ok,
                $tx_err
            );
            if (!$tx_ok) {
                throw new Exception($tx_err !== '' ? $tx_err : 'Stone stock allocation failed.');
            }
        }
    }
}
