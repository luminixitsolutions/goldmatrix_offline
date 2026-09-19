<?php
/**
 * Next sequential barcode using Product Opening (tbl_product_characteristics) prefix/digits,
 * e.g. GB00001 for Gold Bar + Gold. Scans tbl_stock and tbl_old_jewelry_stock for max numeric suffix.
 */
if (!function_exists('auragold_resolve_product_barcode_prefix_digits')) {
    function auragold_resolve_product_barcode_prefix_digits($conn, $product_id, $characteristic_id, $metal_id, $branch_id)
    {
        $product_id = (int) $product_id;
        $characteristic_id = (int) $characteristic_id;
        $metal_id = (int) $metal_id;
        $branch_id = (int) $branch_id;

        $prefix = '';
        $digits = 0;

        if ($characteristic_id > 0) {
            $pc = getRecord('SELECT barcode_prefix, barcode_digits FROM tbl_product_characteristics WHERE id = ' . $characteristic_id . ' AND status = 1 LIMIT 1');
            if ($pc) {
                $prefix = trim((string) ($pc['barcode_prefix'] ?? ''));
                $digits = (int) ($pc['barcode_digits'] ?? 0);
            }
        }

        if (($prefix === '' || $digits <= 0) && $product_id > 0 && $metal_id > 0) {
            $pc = null;
            if ($branch_id > 0) {
                $pc = getRecord("SELECT barcode_prefix, barcode_digits FROM tbl_product_characteristics WHERE product_id = $product_id AND metal_id = $metal_id AND branch_id = $branch_id AND status = 1 ORDER BY id DESC LIMIT 1");
            }
            if (!$pc) {
                $pc = getRecord("SELECT barcode_prefix, barcode_digits FROM tbl_product_characteristics WHERE product_id = $product_id AND metal_id = $metal_id AND status = 1 ORDER BY id DESC LIMIT 1");
            }
            if ($pc) {
                if ($prefix === '') {
                    $prefix = trim((string) ($pc['barcode_prefix'] ?? ''));
                }
                if ($digits <= 0) {
                    $digits = (int) ($pc['barcode_digits'] ?? 0);
                }
            }
        }

        if (($prefix === '' || $digits <= 0) && $product_id > 0) {
            $pc = getRecord("SELECT barcode_prefix, barcode_digits FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 ORDER BY id ASC LIMIT 1");
            if ($pc) {
                if ($prefix === '') {
                    $prefix = trim((string) ($pc['barcode_prefix'] ?? ''));
                }
                if ($digits <= 0) {
                    $digits = (int) ($pc['barcode_digits'] ?? 0);
                }
            }
        }

        if ($prefix === '' || !preg_match('/^[A-Za-z0-9]{1,15}$/', $prefix)) {
            $prefix = 'RN';
        }
        if ($digits < 1 || $digits > 12) {
            $digits = 5;
        }

        return [$prefix, $digits];
    }
}

if (!function_exists('auragold_next_barcode_for_prefix')) {
    /**
     * Next unique barcode for prefix+digits (sale order items, stock, invoices, in-request reserved list).
     *
     * @param array<int, string> $used_barcodes
     */
    function auragold_next_barcode_for_prefix($conn, $prefix, $digits, array $used_barcodes = [])
    {
        $prefix = trim((string) $prefix);
        $digits = (int) $digits;
        if ($prefix === '' || !preg_match('/^[A-Za-z0-9]{1,15}$/', $prefix)) {
            $prefix = 'RN';
        }
        if ($digits < 1 || $digits > 12) {
            $digits = 5;
        }

        if (function_exists('generateBarcode')) {
            return generateBarcode($conn, $prefix, $digits, $used_barcodes);
        }

        $pat = '/^' . preg_quote($prefix, '/') . '([0-9]+)$/';
        $max = 0;
        $like = mysqli_real_escape_string($conn, $prefix) . '%';

        $tstk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if ($tstk && mysqli_num_rows($tstk) > 0) {
            mysqli_free_result($tstk);
            $rows = getList("SELECT barcode FROM tbl_stock WHERE barcode IS NOT NULL AND TRIM(barcode) != '' AND barcode LIKE '$like'");
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $b = (string) ($r['barcode'] ?? '');
                    if (preg_match($pat, $b, $m)) {
                        $max = max($max, (int) $m[1]);
                    }
                }
            }
        }

        $told = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_stock'");
        if ($told && mysqli_num_rows($told) > 0) {
            mysqli_free_result($told);
            $rows = getList("SELECT barcode FROM tbl_old_jewelry_stock WHERE barcode IS NOT NULL AND TRIM(barcode) != '' AND barcode LIKE '$like'");
            if (is_array($rows)) {
                foreach ($rows as $r) {
                    $b = (string) ($r['barcode'] ?? '');
                    if (preg_match($pat, $b, $m)) {
                        $max = max($max, (int) $m[1]);
                    }
                }
            }
        }

        if (!empty($used_barcodes)) {
            foreach ($used_barcodes as $ub) {
                $ub = trim((string) $ub);
                if ($ub !== '' && preg_match($pat, $ub, $m)) {
                    $max = max($max, (int) $m[1]);
                }
            }
        }

        $next = $max + 1;
        $numStr = (string) $next;

        return $prefix . str_pad($numStr, $digits, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('auragold_next_product_stock_barcode')) {
    /**
     * @param array<int, string> $used_barcodes Barcodes already assigned in this request (product lines, prior ME rows, etc.)
     */
    function auragold_next_product_stock_barcode($conn, $product_id, $characteristic_id, $metal_id, $branch_id, array $used_barcodes = [])
    {
        list($prefix, $digits) = auragold_resolve_product_barcode_prefix_digits($conn, $product_id, $characteristic_id, $metal_id, $branch_id);
        $barcode = auragold_next_barcode_for_prefix($conn, $prefix, $digits, $used_barcodes);

        return [
            'barcode' => $barcode,
            'prefix' => $prefix,
            'digits' => $digits,
        ];
    }
}
