<?php
/**
 * GST helpers: intra-state (CGST+SGST) vs inter-state (IGST) using branch vs customer state.
 * Product opening can enable CGST, SGST, and IGST together; invoice logic picks local sum OR IGST sum, never all three.
 * Product tax rows are split by tbl_tax_master.gst_supply_scope (local_state vs out_of_state), with heuristic by tax_type name.
 */

if (!function_exists('auragold_normalize_state_label')) {
    function auragold_normalize_state_label($s): string
    {
        $s = trim(preg_replace('/\s+/u', ' ', (string) $s));
        if ($s === '') {
            return '';
        }
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($s, 'UTF-8');
        }

        return strtolower($s);
    }
}

if (!function_exists('auragold_gst_states_equal')) {
    function auragold_gst_states_equal(?string $a, ?string $b): bool
    {
        return auragold_normalize_state_label($a ?? '') === auragold_normalize_state_label($b ?? '');
    }
}

if (!function_exists('auragold_gst_resolve_state_id')) {
    /**
     * Resolve a free-text state label to tbl_states.id (case-insensitive name match).
     */
    function auragold_gst_resolve_state_id($conn, ?string $label): int
    {
        if (!$conn || $label === null || trim((string) $label) === '') {
            return 0;
        }
        $norm = auragold_normalize_state_label($label);
        if ($norm === '') {
            return 0;
        }
        $esc = mysqli_real_escape_string($conn, $norm);
        $r = @getRecord("SELECT id FROM tbl_states WHERE status = 1 AND LOWER(TRIM(name)) = '$esc' LIMIT 1");
        if ($r && !empty($r['id'])) {
            return (int) $r['id'];
        }

        return 0;
    }
}

if (!function_exists('auragold_gst_is_interstate_transaction')) {
    /**
     * True when both states are non-empty and different (inter-state / IGST).
     * Empty or invalid: treated as intra-state for CGST+SGST split (conservative for same total tax).
     *
     * @param mixed $conn Optional mysqli connection; when set, compares tbl_states ids when both labels resolve.
     */
    function auragold_gst_is_interstate_transaction(?string $ownerState, ?string $customerState, $conn = null): bool
    {
        $o = auragold_normalize_state_label($ownerState);
        $c = auragold_normalize_state_label($customerState);
        if ($o === '' || $c === '') {
            return false;
        }
        if ($conn && function_exists('auragold_gst_resolve_state_id')) {
            $oid = auragold_gst_resolve_state_id($conn, $ownerState);
            $cid = auragold_gst_resolve_state_id($conn, $customerState);
            if ($oid > 0 && $cid > 0) {
                return $oid !== $cid;
            }
        }

        return $o !== $c;
    }
}

if (!function_exists('auragold_tax_master_has_gst_supply_scope')) {
    function auragold_tax_master_has_gst_supply_scope($conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_tax_master LIKE 'gst_supply_scope'");
        $cache = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }

        return $cache;
    }
}

if (!function_exists('auragold_product_tax_has_branch_id_column')) {
    function auragold_product_tax_has_branch_id_column($conn): bool
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_tax LIKE 'branch_id'");
        $cache = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }

        return $cache;
    }
}

if (!function_exists('auragold_tbl_product_tax_branch_scope_sql')) {
    /**
     * Restrict tbl_product_tax rows to the login / working branch when branch_id exists.
     * Prefer rows for $branch_id; if none exist for this product, fall back to legacy (NULL/0) rows only.
     *
     * @param int|null $branch_id null = no extra filter (legacy callers); int = apply branch scope
     * @return string SQL fragment starting with space + AND (empty when no filter)
     */
    function auragold_tbl_product_tax_branch_scope_sql($conn, int $product_id, ?int $branch_id): string
    {
        $product_id = (int) $product_id;
        if ($product_id <= 0 || !$conn || $branch_id === null) {
            return '';
        }
        if (!auragold_product_tax_has_branch_id_column($conn)) {
            return '';
        }
        $bid = (int) $branch_id;
        if ($bid <= 0) {
            return ' AND (pt.branch_id IS NULL OR pt.branch_id = 0)';
        }

        return ' AND (
            pt.branch_id = ' . $bid . '
            OR (
                (pt.branch_id IS NULL OR pt.branch_id = 0)
                AND NOT EXISTS (
                    SELECT 1 FROM tbl_product_tax ptscope
                    WHERE ptscope.product_id = ' . $product_id . '
                    AND ptscope.branch_id = ' . $bid . '
                    AND (ptscope.status = 1 OR ptscope.status IS NULL)
                )
            )
        )';
    }
}

if (!function_exists('auragold_product_gst_heuristic_sums')) {
    /**
     * Sum CGST+SGST vs IGST from tbl_product_tax.tax_type alone (no Tax Master join).
     * Avoids using legacy_total = CGST+SGST+IGST (double-count) when master row names do not match.
     *
     * @param int|null $branch_id Login / working branch; null = all rows (legacy)
     * @return array{local: float, interstate: float}
     */
    function auragold_product_gst_heuristic_sums($conn, int $product_id, ?int $branch_id = null): array
    {
        $local = 0.0;
        $inter = 0.0;
        if ($product_id <= 0 || !$conn) {
            return ['local' => $local, 'interstate' => $inter];
        }
        $pid = (int) $product_id;
        $scope = auragold_tbl_product_tax_branch_scope_sql($conn, $pid, $branch_id);
        $rows = @getList(
            'SELECT pt.tax_type, pt.tax_value FROM tbl_product_tax pt WHERE pt.product_id = ' . $pid
            . ' AND (pt.status = 1 OR pt.status IS NULL)' . $scope
        );
        if (!is_array($rows)) {
            return ['local' => $local, 'interstate' => $inter];
        }
        foreach ($rows as $r) {
            $tn = auragold_normalize_state_label((string) ($r['tax_type'] ?? ''));
            $tv = (float) ($r['tax_value'] ?? 0);
            if ($tn === 'igst') {
                $inter += $tv;
            } elseif ($tn === 'cgst' || $tn === 'sgst') {
                $local += $tv;
            }
        }

        return ['local' => $local, 'interstate' => $inter];
    }
}

if (!function_exists('auragold_product_gst_mirror_fill')) {
    /** When only one side is set, mirror combined % to the other (same effective GST slab). */
    function auragold_product_gst_mirror_fill(array $out): array
    {
        if ($out['local'] <= 0 && $out['interstate'] > 0) {
            $out['local'] = $out['interstate'];
        } elseif ($out['interstate'] <= 0 && $out['local'] > 0) {
            $out['interstate'] = $out['local'];
        }

        return $out;
    }
}

if (!function_exists('auragold_product_gst_invoice_slab_percent_from_scopes')) {
    /**
     * Single GST % applied on invoice line (same total tax local vs interstate).
     * max(CGST+SGST %, IGST %) — not sum of all enabled checkboxes (3+3+6).
     */
    function auragold_product_gst_invoice_slab_percent_from_scopes(array $scopes): float
    {
        $l = (float) ($scopes['local'] ?? 0);
        $i = (float) ($scopes['interstate'] ?? 0);
        if ($l <= 0 && $i <= 0) {
            return (float) ($scopes['legacy_total'] ?? 0);
        }

        return max($l, $i);
    }
}

if (!function_exists('auragold_calculate_gst_amounts')) {
    /**
     * Monetary GST split: local = half slab to CGST + half to SGST; interstate = full slab to IGST.
     *
     * @return array{cgst: float, sgst: float, igst: float, total_tax: float, type: string}
     */
    function auragold_calculate_gst_amounts(float $amount, float $gstPercentTotal, ?string $ownerState, ?string $customerState, $conn = null): array
    {
        $result = [
            'cgst' => 0.0,
            'sgst' => 0.0,
            'igst' => 0.0,
            'total_tax' => 0.0,
            'type' => '',
        ];
        $o = auragold_normalize_state_label((string) ($ownerState ?? ''));
        $c = auragold_normalize_state_label((string) ($customerState ?? ''));
        if ($o === '' || $c === '') {
            return $result;
        }
        $gstPercentTotal = max(0.0, $gstPercentTotal);
        $amount = max(0.0, $amount);
        if (auragold_gst_is_interstate_transaction($ownerState, $customerState, $conn)) {
            $result['igst'] = round($amount * $gstPercentTotal / 100, 2);
            $result['type'] = 'INTERSTATE';
        } else {
            $half = $gstPercentTotal / 2;
            $result['cgst'] = round($amount * $half / 100, 2);
            $result['sgst'] = round($amount * $half / 100, 2);
            $result['type'] = 'LOCAL';
        }
        $result['total_tax'] = $result['cgst'] + $result['sgst'] + $result['igst'];

        return $result;
    }
}

if (!function_exists('auragold_product_gst_percent_by_supply_scope')) {
    /**
     * @param int|null $branch_id Login / working branch for tbl_product_tax.branch_id; null = all rows (legacy)
     * @return array{local: float, interstate: float, legacy_total: float}
     */
    function auragold_product_gst_percent_by_supply_scope($conn, int $product_id, ?int $branch_id = null): array
    {
        $product_id = (int) $product_id;
        $out = ['local' => 0.0, 'interstate' => 0.0, 'legacy_total' => 0.0];
        if ($product_id <= 0) {
            return $out;
        }

        $scope = auragold_tbl_product_tax_branch_scope_sql($conn, $product_id, $branch_id);
        $legacy = getRecord(
            'SELECT COALESCE(SUM(pt.tax_value), 0) AS t FROM tbl_product_tax pt WHERE pt.product_id = ' . $product_id
            . ' AND (pt.status = 1 OR pt.status IS NULL)' . $scope
        );
        $out['legacy_total'] = $legacy && isset($legacy['t']) ? (float) $legacy['t'] : 0.0;

        $heuristic = auragold_product_gst_heuristic_sums($conn, $product_id, $branch_id);

        if (!auragold_tax_master_has_gst_supply_scope($conn)) {
            if ($heuristic['local'] > 0 || $heuristic['interstate'] > 0) {
                $out['local'] = $heuristic['local'];
                $out['interstate'] = $heuristic['interstate'];

                return auragold_product_gst_mirror_fill($out);
            }
            $out['local'] = $out['legacy_total'];
            $out['interstate'] = $out['legacy_total'];

            return $out;
        }

        $sql = "
            SELECT
                COALESCE(SUM(CASE WHEN tm.id IS NOT NULL AND IFNULL(tm.gst_supply_scope, 'local_state') = 'local_state' THEN pt.tax_value ELSE 0 END), 0) AS local_sum,
                COALESCE(SUM(CASE WHEN tm.id IS NOT NULL AND tm.gst_supply_scope = 'out_of_state' THEN pt.tax_value ELSE 0 END), 0) AS inter_sum
            FROM tbl_product_tax pt
            LEFT JOIN tbl_tax_master tm ON TRIM(tm.name) COLLATE utf8mb4_unicode_ci = TRIM(pt.tax_type) COLLATE utf8mb4_unicode_ci AND tm.status = 1
            WHERE pt.product_id = $product_id AND (pt.status = 1 OR pt.status IS NULL)
        " . $scope;
        $row = getRecord($sql);
        if ($row) {
            $out['local'] = (float) ($row['local_sum'] ?? 0);
            $out['interstate'] = (float) ($row['inter_sum'] ?? 0);
        }

        // Breakdown (tax_type CGST/SGST vs IGST) corrects IFNULL-gst_scope SQL that can count IGST in local_sum.
        if (function_exists('auragold_product_gst_tax_breakdown')) {
            $bd = auragold_product_gst_tax_breakdown($conn, $product_id, $branch_id);
            $lbd = 0.0;
            $ibd = 0.0;
            foreach ($bd['local_state'] ?? [] as $x) {
                $lbd += (float) ($x['default_value'] ?? 0);
            }
            foreach ($bd['out_of_state'] ?? [] as $x) {
                $ibd += (float) ($x['default_value'] ?? 0);
            }
            if ($lbd > 0.00001 || $ibd > 0.00001) {
                $out['local'] = $lbd;
                $out['interstate'] = $ibd;
            }
        }

        if ($out['local'] <= 0 && $out['interstate'] <= 0) {
            if ($heuristic['local'] > 0 || $heuristic['interstate'] > 0) {
                $out['local'] = $heuristic['local'];
                $out['interstate'] = $heuristic['interstate'];
            }
            // When gst_supply_scope exists, do not mirror legacy_total onto both sides (avoids treating CGST+SGST+IGST as one slab).
        }

        return $out;
    }
}

if (!function_exists('auragold_product_gst_tax_breakdown')) {
    /**
     * Per-row GST from tbl_product_tax split by tbl_tax_master.gst_supply_scope for API/UI.
     * Rows without a Tax Master match are classified by tax_type name (CGST/SGST vs IGST).
     *
     * @param int|null $branch_id Login / working branch for tbl_product_tax.branch_id; null = all rows (legacy)
     * @return array{local_state: list<array{name: string, default_value: float, gst_supply_scope: string}>, out_of_state: list<array{name: string, default_value: float, gst_supply_scope: string}>}
     */
    function auragold_product_gst_tax_breakdown($conn, int $product_id, ?int $branch_id = null): array
    {
        $out = ['local_state' => [], 'out_of_state' => []];
        if ($product_id <= 0 || !$conn) {
            return $out;
        }
        $pid = (int) $product_id;
        $branchScopeSql = auragold_tbl_product_tax_branch_scope_sql($conn, $pid, $branch_id);
        $rows = @getList(
            "
            SELECT pt.tax_type, pt.tax_value, tm.gst_supply_scope, tm.name AS master_name
            FROM tbl_product_tax pt
            LEFT JOIN tbl_tax_master tm ON TRIM(tm.name) COLLATE utf8mb4_unicode_ci = TRIM(pt.tax_type) COLLATE utf8mb4_unicode_ci AND tm.status = 1
            WHERE pt.product_id = $pid AND (pt.status = 1 OR pt.status IS NULL)
            " . $branchScopeSql
        );
        if (!is_array($rows)) {
            return $out;
        }
        foreach ($rows as $r) {
            $tv = (float) ($r['tax_value'] ?? 0);
            $name = trim((string) ($r['tax_type'] ?? ''));
            $tmScope = isset($r['gst_supply_scope']) ? trim((string) $r['gst_supply_scope']) : '';
            $mn = trim((string) ($r['master_name'] ?? ''));
            $item = [
                'name' => $name !== '' ? $name : $mn,
                'default_value' => $tv,
                'gst_supply_scope' => '',
            ];
            if ($mn !== '') {
                // tax_type name wins for standard GST rows — Tax Master may use IFNULL(...,'local_state') and mis-bucket IGST.
                $tn0 = auragold_normalize_state_label($name);
                if ($tn0 === 'igst') {
                    $item['gst_supply_scope'] = 'out_of_state';
                    $out['out_of_state'][] = $item;

                    continue;
                }
                if ($tn0 === 'cgst' || $tn0 === 'sgst') {
                    $item['gst_supply_scope'] = 'local_state';
                    $out['local_state'][] = $item;

                    continue;
                }
                $eff = $tmScope !== '' ? $tmScope : 'local_state';
                $item['gst_supply_scope'] = $eff;
                if ($eff === 'out_of_state') {
                    $out['out_of_state'][] = $item;
                } else {
                    $out['local_state'][] = $item;
                }

                continue;
            }
            $tn = auragold_normalize_state_label($name);
            if ($tn === 'igst') {
                $item['gst_supply_scope'] = 'out_of_state';
                $out['out_of_state'][] = $item;
            } elseif ($tn === 'cgst' || $tn === 'sgst') {
                $item['gst_supply_scope'] = 'local_state';
                $out['local_state'][] = $item;
            }
        }

        return $out;
    }
}

if (!function_exists('auragold_branch_profile_state_name')) {
    /**
     * Shop / owner state name from My Profile (tbl_branches.profile_state_id → tbl_states.name).
     */
    function auragold_branch_profile_state_name($conn, $branch_id): string
    {
        $branch_id = (int) $branch_id;
        if ($branch_id <= 0) {
            return '';
        }
        $br = null;
        if (function_exists('getRecordMaster')) {
            $br = @getRecordMaster('SELECT profile_state_id FROM tbl_branches WHERE id = ' . $branch_id . ' LIMIT 1');
        }
        if (!$br) {
            $br = @getRecord('SELECT profile_state_id FROM tbl_branches WHERE id = ' . $branch_id . ' LIMIT 1');
        }
        $sid = (int) ($br['profile_state_id'] ?? 0);
        if ($sid <= 0) {
            return '';
        }
        $st = @getRecord('SELECT name FROM tbl_states WHERE id = ' . $sid . ' LIMIT 1');
        if ($st && !empty($st['name'])) {
            return trim((string) $st['name']);
        }

        return '';
    }
}

if (!function_exists('auragold_is_valid_gstin')) {
    /**
     * Indian GSTIN format: 15 chars (state code + PAN-based + entity + Z + checksum).
     */
    function auragold_is_valid_gstin(?string $gst): bool
    {
        $g = strtoupper(preg_replace('/\s+/', '', (string) $gst));
        if (strlen($g) !== 15) {
            return false;
        }

        return (bool) preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[A-Z0-9]{3}$/', $g);
    }
}

if (!function_exists('auragold_gst_split_totals_from_line_tax')) {
    /**
     * @return array{cgst: float, sgst: float, igst: float, mode: string}
     */
    function auragold_gst_split_totals_from_line_tax(float $total_tax, bool $interstate): array
    {
        $total_tax = max(0, $total_tax);
        if ($interstate) {
            return ['cgst' => 0.0, 'sgst' => 0.0, 'igst' => $total_tax, 'mode' => 'interstate'];
        }

        $half = round($total_tax / 2, 2);
        $other = round($total_tax - $half, 2);

        return ['cgst' => $half, 'sgst' => $other, 'igst' => 0.0, 'mode' => 'intrastate'];
    }
}
