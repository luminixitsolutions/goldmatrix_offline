<?php

/**
 * Column definitions, user prefs, and table chrome for gold-and-silver.php + AJAX rows.
 */

function gas_ensure_user_column_pref_width_column($conn) {
    static $done = false;
    if ($done || !$conn instanceof mysqli) {
        return;
    }
    $done = true;
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_user_column_preferences` LIKE 'column_width_px'");
    if ($r && mysqli_num_rows($r) > 0) {
        mysqli_free_result($r);
        return;
    }
    if ($r) {
        mysqli_free_result($r);
    }
    @mysqli_query(
        $conn,
        "ALTER TABLE `tbl_user_column_preferences` ADD COLUMN `column_width_px` SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Optional width in pixels' AFTER `is_visible`"
    );
}

function gas_user_column_prefs_has_width_col($conn) {
    $r = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_user_column_preferences` LIKE 'column_width_px'");
    $ok = $r && mysqli_num_rows($r) > 0;
    if ($r) {
        mysqli_free_result($r);
    }
    return $ok;
}

/**
 * @param array<string, array> $extra_field_defs
 * @return array<string, mixed>
 */
function gas_build_stock_list_table_context(mysqli $conn, string $tab, array $extra_field_defs = []): array
{
    $tab = strtolower(trim($tab));
    if (!in_array($tab, ['gold', 'silver', 'all'], true)) {
        $tab = 'gold';
    }

    $gas_columns = [
        'imageUrls' => 'imageUrls',
        'info' => 'info',
        'huid' => 'HUID No.',
        'barcode' => 'Barcode No',
        'product_name' => 'Product Name',
        'location' => 'Location',
        'weight' => 'Weight',
        'gross_wt' => 'Gross Wt',
        'purity_wt' => 'Purity Wt',
        'qty' => 'Qty',
        'carat' => 'Carat',
        'active' => 'active',
        'voucher_type' => 'Voucher Type',
        'invoice_no' => 'Invoice No.',
        'supplier_name' => 'Supplier Name',
        'category' => 'Category',
        'article' => 'Article',
        'metal_cost' => 'Metal Cost',
        'making_cost' => 'Making Cost',
        'stone_wt' => 'Stone Wt',
        'net_wt' => 'Net Wt',
        'barcoded_date' => 'Barcoded Date',
        'making_charge_amt' => 'Making Charge Amt.',
        'stone_cost' => 'Stone Cost',
        'purchase_amount' => 'Purchase Amount',
        'making_type' => 'Making Type',
        'metal_value' => 'Metal Value',
        'stone_rate' => 'Stone Rate',
        'stone_charge_type' => 'Stone Charge Type',
        'stone_amt' => 'Stone Amt.',
        'making_charge_rate' => 'Making Charge Rate',
        'wastage_wt' => 'Wastage Wt',
        'wastage_per' => 'Wastage Per.',
    ];

    $gas_column_group_defs = [
        'media' => [
            'label' => 'Media &amp; notes',
            'keys' => ['imageUrls', 'info'],
        ],
        'ident' => [
            'label' => 'Product &amp; IDs',
            'keys' => ['huid', 'barcode', 'product_name', 'location', 'category', 'article'],
        ],
        'weight' => [
            'label' => 'Weight &amp; quantity',
            'keys' => ['weight', 'gross_wt', 'purity_wt', 'net_wt', 'qty', 'carat', 'stone_wt', 'wastage_wt', 'wastage_per'],
        ],
        'status' => [
            'label' => 'Status &amp; document',
            'keys' => ['active', 'voucher_type', 'invoice_no', 'supplier_name', 'barcoded_date'],
        ],
        'value' => [
            'label' => 'Value &amp; cost',
            'keys' => ['metal_cost', 'making_cost', 'stone_cost', 'purchase_amount', 'making_charge_amt', 'stone_amt', 'metal_value', 'stone_rate', 'making_charge_rate', 'making_type', 'stone_charge_type'],
        ],
    ];

    foreach ($extra_field_defs as $ef_key => $ef_meta) {
        $gas_columns[$ef_key] = (string) ($ef_meta['label'] ?? $ef_key);
    }
    if (!empty($extra_field_defs)) {
        $gas_column_group_defs['extra'] = [
            'label' => 'Extra Fields',
            'keys' => array_keys($extra_field_defs),
        ];
    }

    $gas_col_group_info = [];
    foreach ($gas_column_group_defs as $gid => $gdef) {
        $lab_html = (string) $gdef['label'];
        $lab_plain = html_entity_decode(strip_tags($lab_html), ENT_QUOTES, 'UTF-8');
        foreach ($gdef['keys'] as $gkey) {
            if (array_key_exists($gkey, $gas_columns)) {
                $gas_col_group_info[$gkey] = [
                    'id' => $gid,
                    'label' => $lab_html,
                    'label_plain' => $lab_plain,
                ];
            }
        }
    }
    foreach (array_keys($gas_columns) as $gck) {
        if (!isset($gas_col_group_info[$gck])) {
            $gas_col_group_info[$gck] = [
                'id' => 'other',
                'label' => 'Other',
                'label_plain' => 'Other',
            ];
        }
    }

    gas_ensure_user_column_pref_width_column($conn);
    $gas_has_width_db = gas_user_column_prefs_has_width_col($conn);

    $gas_user_id = (int) ($_SESSION['Admin']['id'] ?? ($_SESSION['user_id'] ?? 0));
    $gas_visible_map = [];
    $gas_width_map = [];
    if ($gas_user_id > 0) {
        $gas_tab_esc = mysqli_real_escape_string($conn, $tab);
        $wsel = $gas_has_width_db ? ', column_width_px' : '';
        $gas_pref_rows = getList(
            "SELECT column_key, column_order, is_visible$wsel
             FROM tbl_user_column_preferences
             WHERE user_id = $gas_user_id AND page_name = 'gold-and-silver' AND tab_key = '$gas_tab_esc'
             ORDER BY column_order ASC"
        );
        if (is_array($gas_pref_rows) && $gas_pref_rows !== []) {
            $ordered_cols = [];
            $seen_ck = [];
            foreach ($gas_pref_rows as $pr) {
                $ck = (string) ($pr['column_key'] ?? '');
                if ($ck === '' || !array_key_exists($ck, $gas_columns)) {
                    continue;
                }
                $ordered_cols[$ck] = $gas_columns[$ck];
                $seen_ck[$ck] = true;
                $gas_visible_map[$ck] = ((int) ($pr['is_visible'] ?? 1) === 1) ? 1 : 0;
                if ($gas_has_width_db && isset($pr['column_width_px']) && $pr['column_width_px'] !== null && $pr['column_width_px'] !== '') {
                    $px = (int) $pr['column_width_px'];
                    if ($px >= 40 && $px <= 1200) {
                        $gas_width_map[$ck] = $px;
                    }
                }
            }
            foreach ($gas_columns as $ck => $clab) {
                if (empty($seen_ck[$ck])) {
                    $ordered_cols[$ck] = $clab;
                }
            }
            $gas_columns = $ordered_cols;
        }
    }

    $gas_col_meta = [];
    $gas_visible_count = 0;
    foreach ($gas_columns as $ck => $_clab) {
        $hidden = isset($gas_visible_map[$ck]) && (int) $gas_visible_map[$ck] === 0;
        if (!$hidden) {
            $gas_visible_count++;
        }
        $gas_col_meta[$ck] = [
            'hidden' => $hidden,
            'width' => $gas_width_map[$ck] ?? null,
        ];
    }

    $gas_js_prefs = [
        'pageName' => 'gold-and-silver',
        'tabKey' => $tab,
        'order' => array_keys($gas_columns),
        'visible' => [],
        'widths' => $gas_width_map,
        'userId' => $gas_user_id,
    ];
    foreach (array_keys($gas_columns) as $gck) {
        $gas_js_prefs['visible'][$gck] = !($gas_col_meta[$gck]['hidden'] ?? false);
    }

    $gas_thead_group_runs = [];
    foreach (array_keys($gas_columns) as $gk) {
        if (!empty($gas_col_meta[$gk]['hidden'])) {
            continue;
        }
        $gid = $gas_col_group_info[$gk]['id'] ?? 'other';
        if ($gas_thead_group_runs === []
            || ($gas_thead_group_runs[count($gas_thead_group_runs) - 1]['id'] !== $gid)) {
            $gmeta = $gas_col_group_info[$gk];
            $glab = (string) ($gmeta['label'] ?? 'Other');
            $gas_thead_group_runs[] = [
                'id' => $gid,
                'label' => $glab,
                'n' => 1,
            ];
        } else {
            $i = count($gas_thead_group_runs) - 1;
            $gas_thead_group_runs[$i]['n']++;
        }
    }
    if ($gas_thead_group_runs === [] && $gas_columns !== []) {
        $keys_tmp = array_keys($gas_columns);
        $last_key = $keys_tmp ? (string) $keys_tmp[count($keys_tmp) - 1] : '';
        $last = $last_key !== '' ? ($gas_col_group_info[$last_key] ?? null) : null;
        $glab = is_array($last) && isset($last['label']) ? (string) $last['label'] : '—';
        $gas_thead_group_runs[] = [
            'id' => '—',
            'label' => $glab,
            'n' => (int) max(1, count($gas_columns)),
        ];
    }

    $gas_no_image_src = 'no_image.jpg';
    if (!empty($_SERVER['SCRIPT_NAME'])) {
        $gas_sd = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));
        if ($gas_sd !== '' && $gas_sd !== '/' && $gas_sd !== '.') {
            $gas_no_image_src = rtrim($gas_sd, '/') . '/no_image.jpg';
        }
    }
    $gas_no_image_src_esc = htmlspecialchars($gas_no_image_src, ENT_QUOTES, 'UTF-8');
    $gas_thumb_onerror_attr = ' onerror="this.onerror=null;this.src=' . json_encode($gas_no_image_src, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . '"';

    return [
        'columns' => $gas_columns,
        'column_group_defs' => $gas_column_group_defs,
        'col_group_info' => $gas_col_group_info,
        'col_meta' => $gas_col_meta,
        'visible_count' => $gas_visible_count,
        'js_prefs' => $gas_js_prefs,
        'thead_group_runs' => $gas_thead_group_runs,
        'no_image_src' => $gas_no_image_src,
        'no_image_src_esc' => $gas_no_image_src_esc,
        'thumb_onerror_attr' => $gas_thumb_onerror_attr,
    ];
}

function gas_stock_list_empty_grand_totals(): array
{
    return [
        'weight' => 0.0,
        'gross_wt' => 0.0,
        'purity_wt' => 0.0,
        'qty' => 0.0,
        'stone_wt' => 0.0,
        'net_wt' => 0.0,
        'wastage_wt' => 0.0,
        'metal_cost' => 0.0,
        'making_cost' => 0.0,
        'making_charge_amt' => 0.0,
        'stone_cost' => 0.0,
        'purchase_amount' => 0.0,
        'metal_value' => 0.0,
        'stone_amt' => 0.0,
    ];
}
