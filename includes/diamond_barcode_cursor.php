<?php
/**
 * Diamond barcode merge: multiple tbl_purchase_invoice_items rows can share one barcode.
 * Procedural cursor (same sequence as SQL DECLARE/OPEN/FETCH/WHILE/CLOSE/DEALLOCATE):
 *  1. Barcode variable
 *  2. Cursor = SELECT from purchase lines (diamond/stone) for that barcode
 *  3. OPEN / 4. FETCH first / 5–8. WHILE: build each sale payload (logical "insert into temp line list")
 *  9. CLOSE / 10. DEALLOCATE → implemented as one result set + PHP loop.
 *
 * "Unsold": no tbl_sale_invoice_items row for (barcode + product_id + product_characteristic_id),
 * and tbl_stock row (when present) has available qty/weight.
 */

/**
 * @return bool
 */
function auragold_pii_has_diamond_category_column(): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = false;
    // Must use the same connection as getList/getRecord (branch $conn), not $conn_master — schema can differ.
    global $conn, $conn_master;
    $dbc = (isset($conn) && $conn && is_object($conn)) ? $conn : $conn_master;
    if (!$dbc || !is_object($dbc)) {
        return false;
    }
    $r = @mysqli_query($dbc, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'diamond_category'");
    if ($r && mysqli_num_rows($r) > 0) {
        $cached = true;
    }
    if ($r) {
        mysqli_free_result($r);
    }
    return $cached;
}

/**
 * Shared tag on composite purchase lines: physical barcode differs but barcode_no holds the scanned tag (e.g. DIA00001).
 *
 * @return bool
 */
function auragold_pii_has_barcode_no_column(): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = false;
    global $conn, $conn_master;
    $dbc = (isset($conn) && $conn && is_object($conn)) ? $conn : $conn_master;
    if (!$dbc || !is_object($dbc)) {
        return false;
    }
    $r = @mysqli_query($dbc, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'barcode_no'");
    if ($r && mysqli_num_rows($r) > 0) {
        $cached = true;
    }
    if ($r) {
        mysqli_free_result($r);
    }
    return $cached;
}

/**
 * Match purchase lines for a scanned value: physical barcode and/or shared tag (barcode_no).
 *
 * @return string SQL fragment for WHERE (already uses escaped $barcode_esc)
 */
function auragold_pii_sql_barcode_or_tag_match(string $alias, string $barcode_esc): string {
    $a = $alias !== '' ? $alias . '.' : '';
    $base = "TRIM(IFNULL({$a}barcode, '')) = '$barcode_esc'";
    if (!auragold_pii_has_barcode_no_column()) {
        return $base;
    }

    return "(TRIM(IFNULL({$a}barcode, '')) = '$barcode_esc' OR TRIM(IFNULL({$a}barcode_no, '')) = '$barcode_esc')";
}

/**
 * Stock row for a specific purchase invoice line when barcode+product+characteristic repeat (multiple inward rows).
 * Matches n-th purchase line to n-th stock row (ORDER BY id ASC).
 *
 * @return array<string,mixed>|null
 */
function auragold_stock_row_for_purchase_invoice_item(string $barcode_esc, int $purchase_item_id, ?int $branch_id = null): ?array {
    $line = getRecord('SELECT id, product_id, product_characteristic_id FROM tbl_purchase_invoice_items WHERE id = ' . (int) $purchase_item_id . ' AND status = 1 LIMIT 1');
    if (!$line) {
        return null;
    }
    $pid = (int) ($line['product_id'] ?? 0);
    if ($pid <= 0) {
        return null;
    }
    $pcid = isset($line['product_characteristic_id']) && $line['product_characteristic_id'] !== '' && $line['product_characteristic_id'] !== null
        ? (int) $line['product_characteristic_id'] : null;
    $pcid_where_pii = ($pcid !== null && $pcid > 0)
        ? 'AND pii.product_characteristic_id = ' . (int) $pcid
        : 'AND (pii.product_characteristic_id IS NULL OR pii.product_characteristic_id = 0)';
    $pcid_where_s = ($pcid !== null && $pcid > 0)
        ? 'AND s.product_characteristic_id = ' . (int) $pcid
        : 'AND (s.product_characteristic_id IS NULL OR s.product_characteristic_id = 0)';

    $siblings = getList("
        SELECT id FROM tbl_purchase_invoice_items pii
        WHERE TRIM(pii.barcode) = '$barcode_esc' AND pii.product_id = $pid AND pii.status = 1
        $pcid_where_pii
        ORDER BY pii.id ASC
    ");
    $idx = 0;
    if (is_array($siblings)) {
        foreach ($siblings as $s) {
            if ((int) ($s['id'] ?? 0) === $purchase_item_id) {
                break;
            }
            $idx++;
        }
    }

    $branch_sql = ($branch_id !== null && (int) $branch_id > 0) ? ' AND s.branch_id = ' . (int) $branch_id : '';
    $stocks = getList("
        SELECT s.id FROM tbl_stock s
        WHERE TRIM(s.barcode) = '$barcode_esc' AND s.product_id = $pid AND s.status = 1
        $pcid_where_s
        $branch_sql
        ORDER BY s.id ASC
    ");
    if (is_array($stocks) && isset($stocks[$idx]) && (int) ($stocks[$idx]['id'] ?? 0) > 0) {
        $sid = (int) $stocks[$idx]['id'];
        $full = getRecord("
            SELECT s.id, s.product_id, s.product_characteristic_id, s.barcode, s.metal_id,
                   s.current_qty, s.current_weight, s.final_weight, s.rate, s.value,
                   s.opening_purity, s.opening_weight, s.opening_qty
            FROM tbl_stock s WHERE s.id = $sid AND s.status = 1 LIMIT 1
        ");
        if ($full) {
            return $full;
        }
    }

    return auragold_stock_row_for_purchase_line($barcode_esc, $pid, $pcid, $branch_id);
}

/**
 * When tbl_stock has no row yet, build a minimal stock-shaped array from the purchase line so merge can still return a row.
 *
 * @return array<string,mixed>|null
 */
function auragold_synthetic_stock_from_purchase_invoice_item(int $purchase_item_id, string $barcode_esc): ?array {
    $pii = getRecord('
        SELECT product_id, product_characteristic_id, quantity, gross_weight, final_weight, rate, amount, purchase_amount, purity
        FROM tbl_purchase_invoice_items
        WHERE id = ' . (int) $purchase_item_id . ' AND status = 1
        LIMIT 1
    ');
    if (!$pii || (int) ($pii['product_id'] ?? 0) <= 0) {
        return null;
    }
    $gw = isset($pii['gross_weight']) ? (float) $pii['gross_weight'] : 0.0;
    $qty = isset($pii['quantity']) ? (float) $pii['quantity'] : 1.0;
    if ($qty <= 0) {
        $qty = 1.0;
    }
    if ($gw <= 0) {
        $gw = 0.0001;
    }
    $pcid = isset($pii['product_characteristic_id']) && $pii['product_characteristic_id'] !== '' && $pii['product_characteristic_id'] !== null
        ? (int) $pii['product_characteristic_id'] : null;

    return [
        'id' => 0,
        'product_id' => (int) $pii['product_id'],
        'product_characteristic_id' => $pcid,
        'barcode' => $barcode_esc,
        'metal_id' => null,
        'current_qty' => $qty,
        'current_weight' => $gw,
        'final_weight' => $pii['final_weight'] ?? null,
        'rate' => $pii['rate'] ?? null,
        'value' => $pii['amount'] ?? ($pii['purchase_amount'] ?? null),
        'opening_purity' => $pii['purity'] ?? null,
        'opening_weight' => $gw,
        'opening_qty' => $qty,
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function auragold_diamond_purchase_lines_for_barcode(string $barcode_esc): array {
    try {
        $matchWhere = auragold_pii_sql_barcode_or_tag_match('', $barcode_esc);
        $matchWhereX = auragold_pii_sql_barcode_or_tag_match('x', $barcode_esc);
        // 1) Fast path: 2+ active lines with this tag — return every line (no diamond/metal EXISTS filters).
        //    Includes lines linked only via barcode_no (shared tag) as well as same physical barcode.
        $cnt_row = getRecord("
            SELECT COUNT(*) AS c
            FROM tbl_purchase_invoice_items
            WHERE status = 1
            AND $matchWhere
        ");
        $n = $cnt_row ? (int) ($cnt_row['c'] ?? 0) : 0;
        if ($n >= 2) {
            $multi = getList("
                SELECT id AS purchase_item_id,
                       invoice_id,
                       product_id,
                       product_characteristic_id,
                       TRIM(IFNULL(barcode, '')) AS barcode_trim
                FROM tbl_purchase_invoice_items
                WHERE status = 1
                AND $matchWhere
                ORDER BY id ASC
            ");
            if (is_array($multi) && count($multi) >= 2) {
                return $multi;
            }
        }

        // 2) Single-line (or count query failed): diamond/stone / category / stock EXISTS (legacy behaviour).
        $diamond_cat_or = '';
        if (auragold_pii_has_diamond_category_column()) {
            $diamond_cat_or = "
            OR TRIM(IFNULL(pii.diamond_category, '')) IN ('Jewellery', 'Diamonds', 'GemStones')
        ";
        }
        $sql = "
        SELECT pii.id AS purchase_item_id,
               pii.invoice_id,
               pii.product_id,
               pii.product_characteristic_id,
               TRIM(IFNULL(pii.barcode, '')) AS barcode_trim
        FROM tbl_purchase_invoice_items pii
        WHERE $matchWhere
        AND pii.status = 1
        AND (
            (SELECT COUNT(*) FROM tbl_purchase_invoice_items x WHERE x.status = 1 AND $matchWhereX) >= 2
            OR ( 1=0 $diamond_cat_or )
            OR EXISTS (
                SELECT 1 FROM tbl_product_characteristics pc
                INNER JOIN tbl_metal m ON m.id = pc.metal_id AND m.status = 1
                WHERE pc.id = pii.product_characteristic_id AND pc.status = 1
                AND (
                    LOWER(m.display_name) LIKE '%diamond%'
                    OR LOWER(m.display_name) LIKE '%stone%'
                )
            )
            OR EXISTS (
                SELECT 1 FROM tbl_stock s2
                INNER JOIN tbl_metal m ON m.id = s2.metal_id AND m.status = 1
                WHERE s2.status = 1
                AND TRIM(IFNULL(s2.barcode, '')) = TRIM(IFNULL(pii.barcode, ''))
                AND s2.product_id = pii.product_id
                AND (s2.product_characteristic_id <=> pii.product_characteristic_id)
                AND (
                    LOWER(m.display_name) LIKE '%diamond%'
                    OR LOWER(m.display_name) LIKE '%stone%'
                )
            )
        )
        ORDER BY pii.id ASC
    ";
        $rows = getList($sql);

        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return bool
 */
function auragold_pii_has_merge_group_index_column(): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = false;
    global $conn, $conn_master;
    $dbc = (isset($conn) && $conn && is_object($conn)) ? $conn : $conn_master;
    if (!$dbc || !is_object($dbc)) {
        return $cached;
    }
    $r = @mysqli_query($dbc, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'merge_group_index'");
    if ($r && mysqli_num_rows($r) > 0) {
        $cached = true;
    }
    if ($r) {
        mysqli_free_result($r);
    }
    return $cached;
}

/**
 * All purchase-invoice lines for a scanned tag (barcode / barcode_no / merge_group_index).
 *
 * @return list<array<string,mixed>>
 */
function auragold_composite_purchase_lines_for_barcode(string $barcode_esc): array
{
    $lines = auragold_diamond_purchase_lines_for_barcode($barcode_esc);
    if (count($lines) >= 2) {
        return $lines;
    }

    try {
        $match = auragold_pii_sql_barcode_or_tag_match('', $barcode_esc);
        $anchor = getRecord("
            SELECT id, invoice_id, merge_group_index, barcode_no
            FROM tbl_purchase_invoice_items
            WHERE status = 1 AND $match
            ORDER BY id DESC
            LIMIT 1
        ");
        if (!$anchor) {
            return $lines;
        }
        $invoice_id = (int) ($anchor['invoice_id'] ?? 0);
        if ($invoice_id <= 0) {
            return $lines;
        }

        $expanded = [];
        if (auragold_pii_has_merge_group_index_column()) {
            $mgi = $anchor['merge_group_index'] ?? null;
            if ($mgi !== null && $mgi !== '') {
                $mg = (int) $mgi;
                $expanded = getList("
                    SELECT id AS purchase_item_id,
                           invoice_id,
                           product_id,
                           product_characteristic_id,
                           TRIM(IFNULL(barcode, '')) AS barcode_trim
                    FROM tbl_purchase_invoice_items
                    WHERE status = 1
                    AND invoice_id = $invoice_id
                    AND merge_group_index = $mg
                    ORDER BY id ASC
                ");
            }
        }

        if (!is_array($expanded) || count($expanded) < 2) {
            $expanded = getList("
                SELECT id AS purchase_item_id,
                       invoice_id,
                       product_id,
                       product_characteristic_id,
                       TRIM(IFNULL(barcode, '')) AS barcode_trim
                FROM tbl_purchase_invoice_items
                WHERE status = 1
                AND invoice_id = $invoice_id
                AND $match
                ORDER BY id ASC
            ");
        }

        if ((!is_array($expanded) || count($expanded) < 2) && auragold_pii_has_barcode_no_column()) {
            $tag = trim((string) ($anchor['barcode_no'] ?? ''));
            if ($tag !== '') {
                $tag_esc = esc($tag);
                $tag_match = auragold_pii_sql_barcode_or_tag_match('', $tag_esc);
                $expanded = getList("
                    SELECT id AS purchase_item_id,
                           invoice_id,
                           product_id,
                           product_characteristic_id,
                           TRIM(IFNULL(barcode, '')) AS barcode_trim
                    FROM tbl_purchase_invoice_items
                    WHERE status = 1
                    AND invoice_id = $invoice_id
                    AND $tag_match
                    ORDER BY id ASC
                ");
            }
        }

        if (is_array($expanded) && count($expanded) >= 2) {
            return $expanded;
        }
    } catch (Throwable $e) {
        return $lines;
    }

    return $lines;
}

/**
 * Physical barcodes for other lines in the same composite purchase group (excluding scanned value).
 *
 * @return list<string>
 */
function auragold_composite_sibling_barcodes_for_scan(string $barcode, string $barcode_esc): array
{
    $scan = trim($barcode);
    $out = [];
    foreach (auragold_composite_purchase_lines_for_barcode($barcode_esc) as $line) {
        $bc = trim((string) ($line['barcode_trim'] ?? ''));
        if ($bc === '' || strcasecmp($bc, $scan) === 0) {
            continue;
        }
        $out[] = $bc;
    }

    return array_values(array_unique($out));
}

function auragold_sale_invoice_line_exists_for_barcode_product(string $barcode_esc, int $product_id, $characteristic_id): bool {
    if ($characteristic_id !== null && $characteristic_id !== '' && (int) $characteristic_id > 0) {
        $cid = (int) $characteristic_id;
        $r = @getRecord("
            SELECT id FROM tbl_sale_invoice_items
            WHERE barcode = '$barcode_esc'
            AND product_id = $product_id
            AND product_characteristic_id = $cid
            LIMIT 1
        ");
        return (bool) $r;
    }
    $r = @getRecord("
        SELECT id FROM tbl_sale_invoice_items
        WHERE barcode = '$barcode_esc'
        AND product_id = $product_id
        AND (product_characteristic_id IS NULL OR product_characteristic_id = 0)
        LIMIT 1
    ");
    return (bool) $r;
}

/**
 * Stock row for this purchase line (same barcode + product + characteristic).
 *
 * @return array<string,mixed>|null
 */
function auragold_stock_row_for_purchase_line(string $barcode_esc, int $product_id, $characteristic_id, ?int $branch_id = null) {
    $branch_sql = ($branch_id !== null && (int) $branch_id > 0) ? ' AND s.branch_id = ' . (int) $branch_id : '';
    if ($characteristic_id !== null && $characteristic_id !== '' && (int) $characteristic_id > 0) {
        $cid = (int) $characteristic_id;
        return getRecord("
            SELECT s.id, s.product_id, s.product_characteristic_id, s.barcode, s.metal_id,
                   s.current_qty, s.current_weight, s.final_weight, s.rate, s.value,
                   s.opening_purity, s.opening_weight, s.opening_qty
            FROM tbl_stock s
            WHERE TRIM(s.barcode) = '$barcode_esc'
            AND s.product_id = $product_id
            AND s.product_characteristic_id = $cid
            AND s.status = 1
            $branch_sql
            ORDER BY s.id DESC
            LIMIT 1
        ");
    }
    return getRecord("
        SELECT s.id, s.product_id, s.product_characteristic_id, s.barcode, s.metal_id,
               s.current_qty, s.current_weight, s.final_weight, s.rate, s.value,
               s.opening_purity, s.opening_weight, s.opening_qty
        FROM tbl_stock s
        WHERE TRIM(s.barcode) = '$barcode_esc'
        AND s.product_id = $product_id
        AND (s.product_characteristic_id IS NULL OR s.product_characteristic_id = 0)
        AND s.status = 1
        $branch_sql
        ORDER BY s.id DESC
        LIMIT 1
    ");
}

function auragold_stock_line_has_inventory(array $stock_check): bool {
    $cq = isset($stock_check['current_qty']) ? (float) $stock_check['current_qty'] : 0;
    $cw = isset($stock_check['current_weight']) ? (float) $stock_check['current_weight'] : 0;
    $oq = isset($stock_check['opening_qty']) ? (float) $stock_check['opening_qty'] : 0;
    $ow = isset($stock_check['opening_weight']) ? (float) $stock_check['opening_weight'] : 0;
    return $cq > 0 || $cw > 0 || $oq > 0 || $ow > 0;
}
