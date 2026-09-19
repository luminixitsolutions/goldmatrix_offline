<?php
/**
 * Default bill series (tbl_bill_series) for a newly created branch: one row per active voucher type,
 * legacy-style prefixes (SI-, OJB-, …) and start_count = 1 so first document is e.g. SI-1.
 */

if (!function_exists('auragold_default_bill_series_prefix_for_voucher_name')) {
    /**
     * Prefix including trailing hyphen, matching admin/config.php legacy defaults where applicable.
     */
    function auragold_default_bill_series_prefix_for_voucher_name($name) {
        $norm = preg_replace('/\s+/', ' ', trim(strtolower((string) $name)));
        $compact = preg_replace('/[^a-z0-9]/', '', $norm);

        static $map = null;
        if ($map === null) {
            $map = [
                'sales invoice'     => 'SI-',
                'sale invoice'      => 'SI-',
                'old jewelry scrap invoice'   => 'OJB-',
                'old jewellery scrap invoice' => 'OJB-',
                'old jewelry scrap'   => 'OJB-',
                'old jewellery scrap' => 'OJB-',
                'purchase invoice'  => 'PI-',
                'consignment in'    => 'CI-',
                'sales quotation'   => 'SQ-',
                'sales order'       => 'SO-',
                'repair order'      => 'RO-',
                'jobwork order'     => 'JWO-',
                'job work order'    => 'JWO-',
                'material issue'    => 'MI-',
                'material receive'  => 'MR-',
                'jobwork invoice'   => 'JWI-',
                'jobwork queue'     => 'JWQ-',
                'job work queue'    => 'JWQ-',
                'purchase quotation' => 'PQ-',
                'sales return'      => 'SR-',
                'purchase return'   => 'PR-',
                'payment voucher'   => 'PV-',
                'receipt voucher'   => 'RV-',
                'sale receipt voucher' => 'SRV-',
                'advance payment'   => 'AP-',
                'advance'           => 'AP-',
                'jewellery catalogue' => 'JC-',
                'jewelry catalogue'   => 'JC-',
                'jewellery catalog'   => 'JC-',
                'jewelry catalog'     => 'JC-',
                'jewellery catelog'   => 'JC-',
                'jewelry catelog'     => 'JC-',
            ];
        }

        if ($norm !== '' && isset($map[$norm])) {
            return $map[$norm];
        }
        if ($compact === 'jobworkqueue') {
            return 'JWQ-';
        }

        $parts = preg_split('/[^a-z0-9]+/i', (string) $name, -1, PREG_SPLIT_NO_EMPTY);
        $abbr = '';
        foreach ($parts as $p) {
            if (strlen($abbr) >= 5) {
                break;
            }
            $abbr .= strtoupper(substr($p, 0, 1));
        }
        if ($abbr === '') {
            $abbr = 'V';
        }
        $abbr = substr($abbr, 0, 5);

        return $abbr . '-';
    }
}

if (!function_exists('auragold_seed_bill_series_for_new_branch')) {
    /**
     * Insert default tbl_bill_series rows for $branchId. Skips voucher types that already have a row for this branch.
     *
     * @param mysqli $conn
     * @param int    $branchId   New tbl_branches.id (sub-branch)
     * @param int    $createdBy  tbl_bill_series.created_by (optional)
     */
    function auragold_seed_bill_series_for_new_branch($conn, $branchId, $createdBy = 0) {
        $branchId = (int) $branchId;
        if ($branchId < 1 || !$conn instanceof mysqli) {
            return;
        }

        $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
        if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
            if ($tableCheck) {
                mysqli_free_result($tableCheck);
            }
            return;
        }
        mysqli_free_result($tableCheck);

        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_bill_series', 'branch_id')) {
            return;
        }

        $types = [];
        $typesRs = @mysqli_query($conn, 'SELECT id, name FROM tbl_voucher_types WHERE status = 1 ORDER BY id ASC');
        if ($typesRs) {
            while ($row = mysqli_fetch_assoc($typesRs)) {
                $types[] = $row;
            }
            mysqli_free_result($typesRs);
        }
        if (empty($types)) {
            return;
        }

        $createdBy = (int) $createdBy;
        $createdBySql = $createdBy > 0 ? (string) $createdBy : 'NULL';

        $usedPrefixes = [];

        foreach ($types as $t) {
            $vtId = (int) ($t['id'] ?? 0);
            if ($vtId < 1) {
                continue;
            }
            $existing = null;
            $exRs     = @mysqli_query(
                $conn,
                'SELECT id FROM tbl_bill_series WHERE status = 1 AND voucher_type_id = ' . $vtId
                . ' AND branch_id = ' . $branchId . ' LIMIT 1'
            );
            if ($exRs && mysqli_num_rows($exRs) > 0) {
                $existing = mysqli_fetch_assoc($exRs);
            }
            if ($exRs) {
                mysqli_free_result($exRs);
            }
            if ($existing) {
                continue;
            }

            $basePrefix = auragold_default_bill_series_prefix_for_voucher_name((string) ($t['name'] ?? ''));
            $prefix = $basePrefix;
            if (isset($usedPrefixes[$prefix])) {
                $prefix = substr(rtrim($basePrefix, '-'), 0, 4) . $vtId . '-';
            }
            $usedPrefixes[$prefix] = true;

            $prefixEsc = mysqli_real_escape_string($conn, $prefix);
            mysqli_query(
                $conn,
                "INSERT INTO tbl_bill_series
                (voucher_type_id, branch_id, prefix, suffix, start_count, status, created_by, created_at)
                VALUES
                ($vtId, $branchId, '$prefixEsc', '', 1, 1, $createdBySql, NOW())"
            );
        }
    }
}
