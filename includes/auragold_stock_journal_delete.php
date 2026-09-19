<?php
/**
 * Delete stock journal entries and all linked stock / barcode rows created by save-stock-journal.php.
 */

if (!function_exists('auragold_product_opening_has_orphan_stock')) {
    /** True when purchase stock rows exist for this opening with no active journal (leftover after delete). */
    function auragold_product_opening_has_orphan_stock(mysqli $conn, int $characteristic_id): bool
    {
        $cid = (int) $characteristic_id;
        if ($cid <= 0) {
            return false;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if (!$t || mysqli_num_rows($t) === 0) {
            if ($t) {
                mysqli_free_result($t);
            }
            return false;
        }
        mysqli_free_result($t);

        $pc = getRecord("SELECT product_id, metal_id FROM tbl_product_characteristics WHERE id = $cid LIMIT 1");
        $pid = $pc ? (int) ($pc['product_id'] ?? 0) : 0;
        $mid = $pc ? (int) ($pc['metal_id'] ?? 0) : 0;
        $scope_parts = ["s.product_characteristic_id = $cid"];
        if ($pid > 0 && $mid > 0) {
            $scope_parts[] = "(s.product_id = $pid AND s.metal_id = $mid)";
        }
        $scope_sql = '(' . implode(' OR ', $scope_parts) . ')';
        $sj_active = auragold_stock_journal_active_sql($conn);

        $has_ref = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_stock', 'reference_type')
            && auragold_tbl_has_column($conn, 'tbl_stock', 'reference_id');

        if ($has_ref) {
            $sql = "
                SELECT 1
                FROM tbl_stock s
                LEFT JOIN tbl_stock_journal sj
                    ON TRIM(sj.barcode) = TRIM(s.barcode)
                   AND ($sj_active)
                WHERE s.stock_type = 'purchase'
                  AND s.barcode IS NOT NULL AND TRIM(s.barcode) <> ''
                  AND $scope_sql
                  AND (
                      (s.reference_type = 'stock_journal' AND (s.reference_id IS NULL OR s.reference_id = 0 OR NOT EXISTS (SELECT 1 FROM tbl_stock_journal sj_ref WHERE sj_ref.id = s.reference_id)))
                      OR (sj.id IS NULL AND (s.reference_type IS NULL OR s.reference_type = '' OR s.reference_type = 'stock_journal'))
                  )
                LIMIT 1
            ";
        } else {
            $sql = "
                SELECT 1
                FROM tbl_stock s
                LEFT JOIN tbl_stock_journal sj
                    ON TRIM(sj.barcode) = TRIM(s.barcode)
                   AND ($sj_active)
                WHERE s.stock_type = 'purchase'
                  AND s.barcode IS NOT NULL AND TRIM(s.barcode) <> ''
                  AND $scope_sql
                  AND sj.id IS NULL
                LIMIT 1
            ";
        }

        $row = getRecord($sql);
        return !empty($row);
    }
}

if (!function_exists('auragold_stock_journal_delete_where_product_opening')) {
    function auragold_stock_journal_delete_where_product_opening(mysqli $conn, int $characteristic_id): string
    {
        $cid = (int) $characteristic_id;
        $pc = getRecord("SELECT id, product_id, metal_id FROM tbl_product_characteristics WHERE id = $cid LIMIT 1");
        $pid = $pc ? (int) ($pc['product_id'] ?? 0) : 0;
        $mid = $pc ? (int) ($pc['metal_id'] ?? 0) : 0;

        $parts = ["product_characteristic_id = $cid"];
        if ($pid > 0 && $mid > 0) {
            $parts[] = "(product_id = $pid AND metal_id = $mid AND (product_characteristic_id IS NULL OR product_characteristic_id = 0 OR product_characteristic_id != $cid))";
        }

        $scope = '(' . implode(' OR ', $parts) . ')';
        $scope .= ' AND (item_id IS NULL OR item_id = 0)';
        $scope .= " AND (comment IS NULL OR comment NOT LIKE 'auragold_doc|src=pi|%')";

        return $scope;
    }
}

if (!function_exists('auragold_stock_journal_active_sql')) {
    function auragold_stock_journal_active_sql(mysqli $conn): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = "status = 'active'";
        $rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_journal LIKE 'status'");
        if ($rc && ($col = mysqli_fetch_assoc($rc))) {
            $type = strtolower((string) ($col['Type'] ?? ''));
            if (strpos($type, 'int') !== false) {
                $cached = '(status = 1 OR status = \'1\')';
            }
        }
        if ($rc) {
            mysqli_free_result($rc);
        }
        return $cached;
    }
}

if (!function_exists('auragold_stock_journal_delete_stock_rows')) {
    /**
     * Remove tbl_stock / inward rows for the given journal ids and barcodes.
     *
     * @return array{stock_deleted:int,inward_deleted:int,restore_qty:float,restore_wt:float}
     */
    function auragold_stock_journal_delete_stock_rows(mysqli $conn, array $journal_ids, array $barcodes, bool $track_restore = false): array
    {
        $stock_deleted = 0;
        $inward_deleted = 0;
        $restore_qty = 0.0;
        $restore_wt = 0.0;

        $journal_ids = array_values(array_unique(array_filter(array_map('intval', $journal_ids))));
        $barcodes = array_values(array_unique(array_filter(array_map(static function ($b) {
            return trim((string) $b);
        }, $barcodes))));

        if ($track_restore && !empty($barcodes)) {
            foreach ($barcodes as $bc) {
                $esc = mysqli_real_escape_string($conn, $bc);
                $sr = getRecord("
                    SELECT COALESCE(NULLIF(current_qty, 0), opening_qty, 0) AS q,
                           COALESCE(NULLIF(current_weight, 0), opening_weight, 0) AS w
                    FROM tbl_stock
                    WHERE stock_type = 'purchase' AND TRIM(barcode) = TRIM('$esc')
                    LIMIT 1
                ");
                if ($sr) {
                    $restore_qty += (float) ($sr['q'] ?? 0);
                    $restore_wt += (float) ($sr['w'] ?? 0);
                }
            }
        }

        if (!empty($journal_ids)) {
            $ids_sql = implode(',', $journal_ids);

            $has_ref = false;
            $rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
            if ($rc && mysqli_num_rows($rc) >= 2) {
                $has_ref = true;
            }
            if ($rc) {
                mysqli_free_result($rc);
            }

            if ($has_ref) {
                mysqli_query($conn, "DELETE FROM tbl_stock WHERE reference_type = 'stock_journal' AND reference_id IN ($ids_sql)");
                $stock_deleted += mysqli_affected_rows($conn);
            }

            $t_inward = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_inward_stock'");
            if ($t_inward && mysqli_num_rows($t_inward) > 0) {
                mysqli_free_result($t_inward);
                mysqli_query($conn, "DELETE FROM tbl_inward_stock WHERE stock_journal_id IN ($ids_sql)");
                $inward_deleted += mysqli_affected_rows($conn);
            } elseif ($t_inward) {
                mysqli_free_result($t_inward);
            }
        }

        if (!empty($barcodes)) {
            $bc_sql = [];
            foreach ($barcodes as $bc) {
                $bc_sql[] = "'" . mysqli_real_escape_string($conn, $bc) . "'";
            }
            $in_bc = implode(',', $bc_sql);
            mysqli_query($conn, "DELETE FROM tbl_stock WHERE stock_type = 'purchase' AND TRIM(barcode) IN ($in_bc)");
            $stock_deleted += mysqli_affected_rows($conn);

            $rcc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_barcodes'");
            $has_ref_bc = ($rcc && mysqli_num_rows($rcc) > 0);
            if ($rcc) {
                mysqli_free_result($rcc);
            }
            if ($has_ref_bc) {
                foreach ($barcodes as $bc) {
                    $bc_esc = mysqli_real_escape_string($conn, $bc);
                    mysqli_query(
                        $conn,
                        "DELETE FROM tbl_stock WHERE stock_type = 'outward' AND reference_barcodes IS NOT NULL AND reference_barcodes != '' AND FIND_IN_SET('$bc_esc', REPLACE(reference_barcodes, ' ', ''))"
                    );
                    $stock_deleted += mysqli_affected_rows($conn);
                }
            }
        }

        return [
            'stock_deleted' => $stock_deleted,
            'inward_deleted' => $inward_deleted,
            'restore_qty' => $restore_qty,
            'restore_wt' => $restore_wt,
        ];
    }
}

if (!function_exists('auragold_stock_journal_purge_orphan_stock')) {
    /**
     * Remove purchase/outward stock rows left behind when journal rows were already deleted.
     */
    function auragold_stock_journal_purge_orphan_stock(mysqli $conn, int $characteristic_id, int $product_id = 0, int $metal_id = 0): array
    {
        $cid = (int) $characteristic_id;
        if ($cid <= 0) {
            return ['stock_deleted' => 0, 'restore_qty' => 0.0, 'restore_wt' => 0.0, 'barcodes' => []];
        }

        if ($product_id <= 0 || $metal_id <= 0) {
            $pc = getRecord("SELECT product_id, metal_id FROM tbl_product_characteristics WHERE id = $cid LIMIT 1");
            $product_id = $pc ? (int) ($pc['product_id'] ?? 0) : 0;
            $metal_id = $pc ? (int) ($pc['metal_id'] ?? 0) : 0;
        }

        $sj_active = auragold_stock_journal_active_sql($conn);
        $scope_parts = ["s.product_characteristic_id = $cid"];
        if ($product_id > 0 && $metal_id > 0) {
            $scope_parts[] = "(s.product_id = $product_id AND s.metal_id = $metal_id)";
        }
        $scope_sql = '(' . implode(' OR ', $scope_parts) . ')';

        $orphan_rows = getList("
            SELECT s.id, TRIM(s.barcode) AS barcode,
                   COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) AS q,
                   COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) AS w
            FROM tbl_stock s
            LEFT JOIN tbl_stock_journal sj
                ON TRIM(sj.barcode) = TRIM(s.barcode)
               AND (" . auragold_stock_journal_active_sql($conn) . ")
            WHERE s.stock_type = 'purchase'
              AND s.barcode IS NOT NULL AND TRIM(s.barcode) <> ''
              AND $scope_sql
              AND (
                  (s.reference_type = 'stock_journal' AND (s.reference_id IS NULL OR s.reference_id = 0 OR NOT EXISTS (SELECT 1 FROM tbl_stock_journal sj_ref WHERE sj_ref.id = s.reference_id)))
                  OR (sj.id IS NULL AND (s.reference_type IS NULL OR s.reference_type = '' OR s.reference_type = 'stock_journal'))
              )
        ");

        $barcodes = [];
        $restore_qty = 0.0;
        $restore_wt = 0.0;
        $ids = [];
        foreach ($orphan_rows as $row) {
            $ids[] = (int) ($row['id'] ?? 0);
            $bc = trim((string) ($row['barcode'] ?? ''));
            if ($bc !== '' && !in_array($bc, $barcodes, true)) {
                $barcodes[] = $bc;
            }
            $restore_qty += (float) ($row['q'] ?? 0);
            $restore_wt += (float) ($row['w'] ?? 0);
        }

        $stock_deleted = 0;
        if (!empty($ids)) {
            $ids_sql = implode(',', array_map('intval', $ids));
            mysqli_query($conn, "DELETE FROM tbl_stock WHERE id IN ($ids_sql)");
            $stock_deleted += mysqli_affected_rows($conn);
        }

        $has_ref = false;
        $rc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
        if ($rc && mysqli_num_rows($rc) >= 2) {
            $has_ref = true;
        }
        if ($rc) {
            mysqli_free_result($rc);
        }
        if ($has_ref) {
            mysqli_query($conn, "
                DELETE s FROM tbl_stock s
                LEFT JOIN tbl_stock_journal sj ON sj.id = s.reference_id
                WHERE s.reference_type = 'stock_journal'
                  AND sj.id IS NULL
            ");
            $stock_deleted += mysqli_affected_rows($conn);
        }

        if (!empty($barcodes)) {
            auragold_stock_journal_delete_stock_rows($conn, [], $barcodes, false);
        }

        $t_inward = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_inward_stock'");
        if ($t_inward && mysqli_num_rows($t_inward) > 0) {
            mysqli_free_result($t_inward);
            mysqli_query($conn, "
                DELETE ins FROM tbl_inward_stock ins
                LEFT JOIN tbl_stock_journal sj ON sj.id = ins.stock_journal_id
                WHERE sj.id IS NULL
            ");
        } elseif ($t_inward) {
            mysqli_free_result($t_inward);
        }

        if (!empty($barcodes)) {
            $root = dirname(__DIR__);
            foreach ($barcodes as $bc) {
                $esc = mysqli_real_escape_string($conn, $bc);
                $imgs = getList("SELECT id, image_path FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc')");
                foreach ($imgs as $img) {
                    $rel = trim((string) ($img['image_path'] ?? ''));
                    if ($rel !== '') {
                        $full = $root . '/' . ltrim(str_replace('\\', '/', $rel), '/');
                        if (is_file($full)) {
                            @unlink($full);
                        }
                    }
                }
                mysqli_query($conn, "DELETE FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc')");
            }
        }

        return [
            'stock_deleted' => $stock_deleted,
            'restore_qty' => $restore_qty,
            'restore_wt' => $restore_wt,
            'barcodes' => $barcodes,
        ];
    }
}

if (!function_exists('auragold_stock_journal_restore_opening_balance')) {
    function auragold_stock_journal_restore_opening_balance(mysqli $conn, int $characteristic_id, float $restore_qty, float $restore_wt): void
    {
        if ($characteristic_id <= 0 || ($restore_qty <= 0 && $restore_wt <= 0)) {
            return;
        }
        $has_qty = false;
        $has_wt = false;
        $upd_cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics WHERE Field IN ('opening_qty','opening_weight')");
        if ($upd_cols) {
            while ($c = mysqli_fetch_assoc($upd_cols)) {
                if (($c['Field'] ?? '') === 'opening_qty') {
                    $has_qty = true;
                }
                if (($c['Field'] ?? '') === 'opening_weight') {
                    $has_wt = true;
                }
            }
            mysqli_free_result($upd_cols);
        }
        $set = [];
        if ($has_qty && $restore_qty > 0) {
            $set[] = 'opening_qty = COALESCE(opening_qty, 0) + ' . (float) $restore_qty;
        }
        if ($has_wt && $restore_wt > 0) {
            $set[] = 'opening_weight = COALESCE(opening_weight, 0) + ' . (float) $restore_wt;
        }
        if (!empty($set)) {
            $cid = (int) $characteristic_id;
            if (!mysqli_query($conn, 'UPDATE tbl_product_characteristics SET ' . implode(', ', $set) . " WHERE id = $cid")) {
                throw new Exception('Failed to restore product opening balance: ' . mysqli_error($conn));
            }
        }
    }
}

if (!function_exists('auragold_delete_stock_journal_entries')) {
    /**
     * Remove stock journal rows and linked tbl_stock / inward / image records.
     *
     * @return array{deleted_count:int,journal_ids:int[],barcodes:string[],stock_deleted:int,orphan_barcodes:string[]}
     */
    function auragold_delete_stock_journal_entries(mysqli $conn, array $opts): array
    {
        $voucher = isset($opts['voucher']) ? trim((string) $opts['voucher']) : '';
        $characteristic_id = isset($opts['characteristic_id']) ? (int) $opts['characteristic_id'] : 0;
        $item_id = isset($opts['item_id']) ? (int) $opts['item_id'] : 0;

        if ($voucher === 'product_opening' && $characteristic_id > 0) {
            $where = auragold_stock_journal_delete_where_product_opening($conn, $characteristic_id);
        } elseif ($item_id > 0) {
            $where = 'item_id = ' . (int) $item_id;
        } else {
            throw new Exception('Invalid delete scope');
        }

        $rows = getList("SELECT id, barcode, quantity, gross_weight, product_characteristic_id, item_id FROM tbl_stock_journal WHERE $where");

        $journal_ids = [];
        $barcodes = [];
        $restore_qty = 0.0;
        $restore_wt = 0.0;

        foreach ($rows as $row) {
            $jid = (int) ($row['id'] ?? 0);
            if ($jid > 0) {
                $journal_ids[] = $jid;
            }
            $bc = trim((string) ($row['barcode'] ?? ''));
            if ($bc !== '' && !in_array($bc, $barcodes, true)) {
                $barcodes[] = $bc;
            }
            if ($voucher === 'product_opening') {
                $restore_qty += (float) ($row['quantity'] ?? 0);
                $restore_wt += (float) ($row['gross_weight'] ?? 0);
            }
        }

        $stock_deleted = 0;
        $deleted_count = 0;

        if (!empty($journal_ids)) {
            $stock_result = auragold_stock_journal_delete_stock_rows(
                $conn,
                $journal_ids,
                $barcodes,
                $voucher !== 'product_opening'
            );
            $stock_deleted += (int) ($stock_result['stock_deleted'] ?? 0);
            if ($voucher !== 'product_opening') {
                $restore_qty += (float) ($stock_result['restore_qty'] ?? 0);
                $restore_wt += (float) ($stock_result['restore_wt'] ?? 0);
            }

            if (!empty($barcodes)) {
                $root = dirname(__DIR__);
                foreach ($barcodes as $bc) {
                    $esc = mysqli_real_escape_string($conn, $bc);
                    $imgs = getList("SELECT id, image_path FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc')");
                    foreach ($imgs as $img) {
                        $rel = trim((string) ($img['image_path'] ?? ''));
                        if ($rel !== '') {
                            $full = $root . '/' . ltrim(str_replace('\\', '/', $rel), '/');
                            if (is_file($full)) {
                                @unlink($full);
                            }
                        }
                    }
                    mysqli_query($conn, "DELETE FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc')");
                }
            }

            $ids_sql = implode(',', array_map('intval', $journal_ids));
            if (!mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE id IN ($ids_sql)")) {
                throw new Exception('Failed to delete stock journal entries: ' . mysqli_error($conn));
            }
            $deleted_count = mysqli_affected_rows($conn);
        }

        $orphan_barcodes = [];
        if ($voucher === 'product_opening' && $characteristic_id > 0) {
            $orphan = auragold_stock_journal_purge_orphan_stock($conn, $characteristic_id);
            $stock_deleted += (int) ($orphan['stock_deleted'] ?? 0);
            $restore_qty += (float) ($orphan['restore_qty'] ?? 0);
            $restore_wt += (float) ($orphan['restore_wt'] ?? 0);
            $orphan_barcodes = is_array($orphan['barcodes'] ?? null) ? $orphan['barcodes'] : [];
            foreach ($orphan_barcodes as $ob) {
                if (!in_array($ob, $barcodes, true)) {
                    $barcodes[] = $ob;
                }
            }
        }

        if ($voucher === 'product_opening' && $characteristic_id > 0) {
            auragold_stock_journal_restore_opening_balance($conn, $characteristic_id, $restore_qty, $restore_wt);
        }

        return [
            'deleted_count' => $deleted_count,
            'journal_ids' => $journal_ids,
            'barcodes' => $barcodes,
            'stock_deleted' => $stock_deleted,
            'orphan_barcodes' => $orphan_barcodes,
        ];
    }
}

if (!function_exists('auragold_delete_barcodes_from_stock')) {
    /**
     * Delete selected barcodes from stock (tbl_stock, journal, images, inward). Restores product opening balance when applicable.
     *
     * @return array{deleted_barcodes:string[],journal_deleted:int,stock_deleted:int,skipped:string[]}
     */
    function auragold_delete_barcodes_from_stock(mysqli $conn, array $barcodes): array
    {
        $barcodes = array_values(array_unique(array_filter(array_map(static function ($b) {
            return trim((string) $b);
        }, $barcodes))));

        if (empty($barcodes)) {
            return ['deleted_barcodes' => [], 'journal_deleted' => 0, 'stock_deleted' => 0, 'skipped' => []];
        }

        $journal_ids = [];
        $restore_by_char = [];
        $skipped = [];

        foreach ($barcodes as $bc) {
            $esc = mysqli_real_escape_string($conn, $bc);
            $jrows = getList("
                SELECT id, quantity, gross_weight, product_characteristic_id, item_id
                FROM tbl_stock_journal
                WHERE TRIM(barcode) = TRIM('$esc')
            ");
            if (empty($jrows)) {
                $has_stock = getRecord("
                    SELECT 1 AS ok FROM tbl_stock
                    WHERE TRIM(barcode) = TRIM('$esc') AND stock_type = 'purchase'
                    LIMIT 1
                ");
                if (!$has_stock) {
                    $skipped[] = $bc;
                    continue;
                }
            }
            foreach ($jrows as $jr) {
                $jid = (int) ($jr['id'] ?? 0);
                if ($jid > 0) {
                    $journal_ids[] = $jid;
                }
                $item_id = (int) ($jr['item_id'] ?? 0);
                $cid = (int) ($jr['product_characteristic_id'] ?? 0);
                if ($item_id <= 0 && $cid > 0) {
                    if (!isset($restore_by_char[$cid])) {
                        $restore_by_char[$cid] = ['qty' => 0.0, 'wt' => 0.0];
                    }
                    $restore_by_char[$cid]['qty'] += (float) ($jr['quantity'] ?? 0);
                    $restore_by_char[$cid]['wt'] += (float) ($jr['gross_weight'] ?? 0);
                }
            }
        }

        $to_delete = array_values(array_diff($barcodes, $skipped));
        if (empty($to_delete)) {
            return ['deleted_barcodes' => [], 'journal_deleted' => 0, 'stock_deleted' => 0, 'skipped' => $skipped];
        }

        $journal_ids = array_values(array_unique(array_filter($journal_ids)));
        $stock_result = auragold_stock_journal_delete_stock_rows($conn, $journal_ids, $to_delete, false);
        $stock_deleted = (int) ($stock_result['stock_deleted'] ?? 0);

        $bc_sql = [];
        foreach ($to_delete as $bc) {
            $bc_sql[] = "'" . mysqli_real_escape_string($conn, $bc) . "'";
        }
        $in_bc = implode(',', $bc_sql);
        mysqli_query($conn, "DELETE FROM tbl_stock WHERE TRIM(barcode) IN ($in_bc)");
        $stock_deleted += mysqli_affected_rows($conn);

        $root = dirname(__DIR__);
        foreach ($to_delete as $bc) {
            $esc = mysqli_real_escape_string($conn, $bc);
            $imgs = getList("SELECT id, image_path FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc')");
            foreach ($imgs as $img) {
                $rel = trim((string) ($img['image_path'] ?? ''));
                if ($rel !== '') {
                    $full = $root . '/' . ltrim(str_replace('\\', '/', $rel), '/');
                    if (is_file($full)) {
                        @unlink($full);
                    }
                }
            }
            mysqli_query($conn, "DELETE FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc')");
        }

        $journal_deleted = 0;
        if (!empty($journal_ids)) {
            $ids_sql = implode(',', array_map('intval', $journal_ids));
            mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE id IN ($ids_sql)");
            $journal_deleted += mysqli_affected_rows($conn);
        }
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE TRIM(barcode) IN ($in_bc)");
        $journal_deleted += mysqli_affected_rows($conn);

        foreach ($restore_by_char as $cid => $amt) {
            auragold_stock_journal_restore_opening_balance(
                $conn,
                (int) $cid,
                (float) ($amt['qty'] ?? 0),
                (float) ($amt['wt'] ?? 0)
            );
        }

        return [
            'deleted_barcodes' => $to_delete,
            'journal_deleted' => $journal_deleted,
            'stock_deleted' => $stock_deleted,
            'skipped' => $skipped,
        ];
    }
}
