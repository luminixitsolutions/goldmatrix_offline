<?php
/**
 * Shared: assign a unique inventory barcode per invoice line on save.
 * Uses tbl_product_characteristics barcode_prefix / barcode_digits (product opening), else tbl_settings.
 */
if (!function_exists('auragold_resolve_unique_invoice_item_barcode')) {
    /**
     * @param mysqli $conn
     * @param array<string,mixed> $item
     * @param array<int,string> $used_barcodes_accumulator in-out: barcodes already taken in this request
     * @param int $exclude_old_jewelry_scrap_item_id Skip this scrap line when checking tbl_old_jewelry_scrap_invoice_items (same row may reuse its barcode until it appears in stock elsewhere).
     * @return string
     */
    function auragold_resolve_unique_invoice_item_barcode($conn, array $item, array &$used_barcodes_accumulator, $exclude_old_jewelry_scrap_item_id = 0)
    {
        $product_id = (int) ($item['product_id'] ?? 0);
        $characteristic_id = isset($item['characteristic_id']) ? (int) $item['characteristic_id'] : 0;
        $metal_id = (int) ($item['metal_id'] ?? 0);
        $branch_id = 0;
        if (!empty($_SESSION['working_branch_id'])) {
            $branch_id = (int) $_SESSION['working_branch_id'];
        } elseif (!empty($_SESSION['branch_id'])) {
            $branch_id = (int) $_SESSION['branch_id'];
        }
        $raw = trim((string) ($item['barcode'] ?? ''));
        $prefix = 'RN';
        $digits = 5;

        if (function_exists('auragold_resolve_product_barcode_prefix_digits') && ($product_id > 0 || $characteristic_id > 0 || $metal_id > 0)) {
            list($prefix, $digits) = auragold_resolve_product_barcode_prefix_digits($conn, $product_id, $characteristic_id, $metal_id, $branch_id);
        } else {
            $pc = null;
            if ($characteristic_id > 0) {
                $pc = getRecord("SELECT barcode_prefix, barcode_digits FROM tbl_product_characteristics WHERE id = $characteristic_id AND status = 1 LIMIT 1");
            }
            if ((!$pc || trim((string) ($pc['barcode_prefix'] ?? '')) === '') && $product_id > 0) {
                $pc = getRecord("SELECT barcode_prefix, barcode_digits FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 ORDER BY id ASC LIMIT 1");
            }
            if ($pc && trim((string) ($pc['barcode_prefix'] ?? '')) !== '') {
                $prefix = trim((string) $pc['barcode_prefix']);
                $digits = (int) ($pc['barcode_digits'] ?? 5);
                if ($digits < 1) {
                    $digits = 5;
                }
            } else {
                $s = getRecord('SELECT barcode_prefix, barcode_digit_length FROM tbl_settings LIMIT 1');
                if ($s) {
                    $p = trim((string) ($s['barcode_prefix'] ?? ''));
                    if ($p !== '') {
                        $prefix = $p;
                    }
                    $d = (int) ($s['barcode_digit_length'] ?? 0);
                    if ($d > 0) {
                        $digits = $d;
                    }
                }
            }
        }

        $ex_oj = (int) $exclude_old_jewelry_scrap_item_id;
        if ($raw === '' || in_array($raw, $used_barcodes_accumulator, true) || auragold_barcode_exists_in_system($conn, $raw, $ex_oj)) {
            $new = generateBarcode($conn, $prefix, $digits, $used_barcodes_accumulator);
            $used_barcodes_accumulator[] = $new;

            return $new;
        }
        $used_barcodes_accumulator[] = $raw;

        return $raw;
    }
}
