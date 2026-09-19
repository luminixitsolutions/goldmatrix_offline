<?php

/**
 * Latest tbl_stock_journal row per barcode — optional columns for sale-order diamond picker.
 *
 * @return array<string, array<string, string>> keyed by trimmed barcode
 */
function auragold_sale_order_diamond_journal_by_barcodes(mysqli $conn, array $barcodes): array
{
    $barcodes = array_values(array_unique(array_filter(array_map('trim', $barcodes))));
    $out = [];
    if ($barcodes === []) {
        return $out;
    }

    $tbl_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal'");
    if (!$tbl_chk || mysqli_num_rows($tbl_chk) === 0) {
        if ($tbl_chk) {
            mysqli_free_result($tbl_chk);
        }

        return $out;
    }
    mysqli_free_result($tbl_chk);

    $lc = [];
    $cr = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_stock_journal');
    $status_col_type = '';
    if ($cr) {
        while ($row = mysqli_fetch_assoc($cr)) {
            $f = strtolower((string) ($row['Field'] ?? ''));
            if ($f !== '') {
                $lc[$f] = $row['Field'];
                if ($f === 'status') {
                    $status_col_type = strtolower((string) ($row['Type'] ?? ''));
                }
            }
        }
        mysqli_free_result($cr);
    }
    if (!isset($lc['barcode']) || !isset($lc['id'])) {
        return $out;
    }

    $status_sql = '1=1';
    if (isset($lc['status'])) {
        if (strpos($status_col_type, 'char') !== false || strpos($status_col_type, 'text') !== false || strpos($status_col_type, 'enum') !== false) {
            $status_sql = "(status = 'active' OR LOWER(TRIM(CAST(status AS CHAR))) = 'active')";
        } else {
            $status_sql = '(status = 1 OR status = \'1\')';
        }
    }

    $pick_first = static function (array $lcmap, array $names): ?string {
        foreach ($names as $n) {
            $k = strtolower($n);
            if (isset($lcmap[$k])) {
                return $lcmap[$k];
            }
        }

        return null;
    };

    $fields = [];
    $add = static function (array &$fields, ?string $sql_field, string $alias) {
        if ($sql_field !== null && $sql_field !== '') {
            $sf = str_replace('`', '', $sql_field);
            $al = str_replace('`', '', $alias);
            $fields[] = 'j.`' . $sf . '` AS `' . $al . '`';
        }
    };

    $add($fields, $lc['barcode'] ?? null, 'barcode');

    $add($fields, $pick_first($lc, ['certificate_no', 'certificate_number', 'cert_no']), 'j_certificate_no');
    $add($fields, $pick_first($lc, ['cut', 'diamond_cut']), 'j_cut');
    $add($fields, $pick_first($lc, ['color', 'diamond_color']), 'j_color');
    $add($fields, $pick_first($lc, ['shape', 'diamond_shape']), 'j_shape');
    $add($fields, $pick_first($lc, ['seivesize', 'sieve_size', 'seive_size']), 'j_seivesize');
    $add($fields, $pick_first($lc, ['size', 'diamond_size', 'stone_size']), 'j_size');
    $add($fields, $pick_first($lc, ['quality', 'clarity', 'diamond_quality']), 'j_quality');
    $add($fields, $pick_first($lc, ['style']), 'j_style');
    $add($fields, $pick_first($lc, ['design_no']), 'j_design_no');
    $add($fields, $pick_first($lc, ['calculation']), 'j_calculation');

    $in_list = [];
    foreach ($barcodes as $b) {
        if ($b !== '') {
            $in_list[] = "'" . mysqli_real_escape_string($conn, $b) . "'";
        }
    }
    if ($in_list === []) {
        return $out;
    }
    $in_sql = implode(',', $in_list);

    $field_sql = implode(",\n            ", $fields);
    $sql = "
        SELECT
            $field_sql
        FROM tbl_stock_journal j
        INNER JOIN (
            SELECT barcode AS sj_bc, MAX(id) AS mid
            FROM tbl_stock_journal
            WHERE ($status_sql)
              AND barcode IS NOT NULL AND TRIM(barcode) <> ''
              AND barcode IN ($in_sql)
            GROUP BY barcode
        ) mx ON mx.mid = j.id AND mx.sj_bc = j.barcode
    ";

    $res = @mysqli_query($conn, $sql);
    if (!$res) {
        return $out;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $bc = trim((string) ($row['barcode'] ?? ''));
        if ($bc === '') {
            continue;
        }
        $style = trim((string) ($row['j_style'] ?? ''));
        if ($style === '') {
            $style = trim((string) ($row['j_design_no'] ?? ''));
        }
        $out[$bc] = [
            'certificate_no' => trim((string) ($row['j_certificate_no'] ?? '')),
            'cut' => trim((string) ($row['j_cut'] ?? '')),
            'color' => trim((string) ($row['j_color'] ?? '')),
            'shape' => trim((string) ($row['j_shape'] ?? '')),
            'seivesize' => trim((string) ($row['j_seivesize'] ?? '')),
            'size' => trim((string) ($row['j_size'] ?? '')),
            'quality' => trim((string) ($row['j_quality'] ?? '')),
            'style' => $style,
            'calculation' => trim((string) ($row['j_calculation'] ?? '')),
        ];
    }
    mysqli_free_result($res);

    return $out;
}
