<?php
/**
 * Runtime voucher-type settings (voucher-type.php) for transaction screens.
 * - Metal allocation: if rows exist for voucher_type_id, only those metal_ids apply; otherwise all metals.
 * - Payment / field visibility: returned for JSON to the browser; missing table/row = no restriction (show all).
 */

if (!function_exists('auragold_get_voucher_type_id_by_name')) {
    function auragold_get_voucher_type_id_by_name($conn, $name)
    {
        $name = trim((string) $name);
        if ($name === '' || !$conn) {
            return 0;
        }
        $q = mysqli_real_escape_string($conn, $name);
        $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = LOWER(TRIM('$q')) LIMIT 1");
        if ($r && !empty($r['id'])) {
            return (int) $r['id'];
        }
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = LOWER(TRIM('$q')) LIMIT 1");
        return ($r2 && !empty($r2['id'])) ? (int) $r2['id'] : 0;
    }
}

if (!function_exists('auragold_voucher_runtime_effective_branch_id')) {
    /**
     * Branch used for voucher-type satellite rows (matches voucher-type.php / ajax when working in a branch).
     */
    function auragold_voucher_runtime_effective_branch_id(): int {
        if (!function_exists('auragold_effective_branch_id')) {
            return 0;
        }
        $bid = (int) auragold_effective_branch_id();
        if ($bid <= 0 && function_exists('auragold_settings_main_branch_id')) {
            $bid = (int) auragold_settings_main_branch_id();
        }
        return $bid;
    }
}

if (!function_exists('auragold_get_voucher_runtime_settings')) {
    function auragold_get_voucher_runtime_settings($conn, $voucher_type_id)
    {
        $voucher_type_id = (int) $voucher_type_id;
        $out = [
            'voucher_type_id' => $voucher_type_id,
            'metal_ids' => null,
            'payment_buttons' => null,
            'field_visibility' => null,
        ];
        if ($voucher_type_id < 1 || !$conn) {
            return $out;
        }

        $bid = function_exists('auragold_voucher_runtime_effective_branch_id')
            ? auragold_voucher_runtime_effective_branch_id()
            : 0;
        $mainBid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;

        $hasMaBr = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_voucher_metal_allocations', 'branch_id');

        $metalRows = [];
        if ($hasMaBr && $bid > 0) {
            $metalRows = getList(
                'SELECT metal_id FROM tbl_voucher_metal_allocations WHERE voucher_type_id = ' . $voucher_type_id
                . ' AND branch_id = ' . (int) $bid
            );
            if ((!is_array($metalRows) || count($metalRows) === 0) && $mainBid > 0 && $mainBid !== $bid) {
                $metalRows = getList(
                    'SELECT metal_id FROM tbl_voucher_metal_allocations WHERE voucher_type_id = ' . $voucher_type_id
                    . ' AND branch_id = ' . (int) $mainBid
                );
            }
        } else {
            $metalRows = getList(
                'SELECT metal_id FROM tbl_voucher_metal_allocations WHERE voucher_type_id = ' . $voucher_type_id
            );
        }

        if (is_array($metalRows) && count($metalRows) > 0) {
            $ids = [];
            foreach ($metalRows as $row) {
                $mid = (int) ($row['metal_id'] ?? 0);
                if ($mid > 0) {
                    $ids[$mid] = true;
                }
            }
            if (count($ids) > 0) {
                $out['metal_ids'] = array_map('intval', array_keys($ids));
            }
        }

        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_voucher_payment_buttons'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);
            $hasPbBr = function_exists('auragold_tbl_has_column')
                && auragold_tbl_has_column($conn, 'tbl_voucher_payment_buttons', 'branch_id');
            $pr = null;
            if ($hasPbBr && $bid > 0) {
                $pr = getRecord(
                    'SELECT * FROM tbl_voucher_payment_buttons WHERE voucher_type_id = ' . $voucher_type_id
                    . ' AND branch_id = ' . (int) $bid . ' LIMIT 1'
                );
                if (!$pr && $mainBid > 0 && $mainBid !== $bid) {
                    $pr = getRecord(
                        'SELECT * FROM tbl_voucher_payment_buttons WHERE voucher_type_id = ' . $voucher_type_id
                        . ' AND branch_id = ' . (int) $mainBid . ' LIMIT 1'
                    );
                }
            } else {
                $pr = getRecord('SELECT * FROM tbl_voucher_payment_buttons WHERE voucher_type_id = ' . $voucher_type_id . ' LIMIT 1');
            }
            if (is_array($pr)) {
                $out['payment_buttons'] = $pr;
            }
        } elseif ($chk) {
            mysqli_free_result($chk);
        }

        $hasFvBr = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_voucher_field_visibility', 'branch_id');
        $fv = null;
        if ($hasFvBr && $bid > 0) {
            $fv = getRecord(
                'SELECT * FROM tbl_voucher_field_visibility WHERE voucher_type_id = ' . $voucher_type_id
                . ' AND branch_id = ' . (int) $bid . ' LIMIT 1'
            );
            if (!$fv && $mainBid > 0 && $mainBid !== $bid) {
                $fv = getRecord(
                    'SELECT * FROM tbl_voucher_field_visibility WHERE voucher_type_id = ' . $voucher_type_id
                    . ' AND branch_id = ' . (int) $mainBid . ' LIMIT 1'
                );
            }
        } else {
            $fv = getRecord('SELECT * FROM tbl_voucher_field_visibility WHERE voucher_type_id = ' . $voucher_type_id . ' LIMIT 1');
        }
        if (is_array($fv)) {
            $out['field_visibility'] = $fv;
        }

        return $out;
    }
}

if (!function_exists('auragold_filter_metals_by_voucher_settings')) {
    function auragold_filter_metals_by_voucher_settings($metals, $runtime)
    {
        if (!is_array($metals)) {
            return [];
        }
        if (empty($runtime['metal_ids']) || !is_array($runtime['metal_ids'])) {
            return $metals;
        }
        $ids = array_flip($runtime['metal_ids']);
        $filtered = array_values(array_filter($metals, function ($m) use ($ids) {
            return isset($ids[(int) ($m['id'] ?? 0)]);
        }));
        return count($filtered) > 0 ? $filtered : $metals;
    }
}

if (!function_exists('auragold_voucher_payment_buttons_defaults')) {
    /** Keys must match tbl_voucher_payment_buttons + ajax/voucher-type.php + auragold-voucher-runtime-apply.js */
    function auragold_voucher_payment_buttons_defaults()
    {
        return [
            'cash' => 1,
            'metal_exchange' => 1,
            'bank' => 1,
            'scrap' => 1,
            'cheque' => 1,
            'add_diamond' => 1,
            'upi' => 1,
            'add_stone' => 1,
            'card' => 1,
            'add_old_jewellery' => 1,
        ];
    }
}

if (!function_exists('auragold_voucher_runtime_client_payload')) {
    /**
     * Safe subset for window.AURAGOLD_VOUCHER_RUNTIME (no server-only keys).
     * Payment flags are merged with defaults so missing DB columns (e.g. card) do not hide icons on the client.
     */
    function auragold_voucher_runtime_client_payload($runtime)
    {
        if (!is_array($runtime)) {
            return [];
        }
        $pb = null;
        if (isset($runtime['payment_buttons']) && is_array($runtime['payment_buttons'])) {
            $pb = array_merge(auragold_voucher_payment_buttons_defaults(), $runtime['payment_buttons']);
        }
        return [
            'voucher_type_id' => (int) ($runtime['voucher_type_id'] ?? 0),
            'payment_buttons' => $pb,
            'field_visibility' => isset($runtime['field_visibility']) && is_array($runtime['field_visibility'])
                ? $runtime['field_visibility'] : null,
        ];
    }
}

if (!function_exists('auragold_voucher_runtime_bootstrap')) {
    /**
     * Filters $metals by voucher allocation and returns JSON payload for the browser.
     *
     * @param resource $conn
     * @param array    $metals
     * @param string   $voucher_display_name e.g. 'Sales Invoice'
     * @return array
     */
    function auragold_voucher_runtime_bootstrap($conn, &$metals, $voucher_display_name)
    {
        $vid = auragold_get_voucher_type_id_by_name($conn, $voucher_display_name);
        $runtime = auragold_get_voucher_runtime_settings($conn, $vid);
        if (is_array($metals)) {
            $metals = auragold_filter_metals_by_voucher_settings($metals, $runtime);
        }
        return auragold_voucher_runtime_client_payload($runtime);
    }
}

if (!function_exists('auragold_voucher_runtime_payload_only')) {
    /**
     * Runtime JSON only (no metal tab filtering). Use when the page has no product modal metals.
     */
    function auragold_voucher_runtime_payload_only($conn, $voucher_display_name)
    {
        $vid = auragold_get_voucher_type_id_by_name($conn, $voucher_display_name);
        $runtime = auragold_get_voucher_runtime_settings($conn, $vid);
        return auragold_voucher_runtime_client_payload($runtime);
    }
}
