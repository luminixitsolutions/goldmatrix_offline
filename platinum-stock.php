<?php
/**
 * Platinum stock — serial stock list (barcode-level) with full journal / cost columns.
 * Scoped to tbl_metal rows with display_name "Platinum" (same as platinum-analysis.php).
 */
session_start();
require_once __DIR__ . '/config.php';

function gas_fmt_num($v, $dec = 3) {
    if ($v === null || $v === '') {
        return '';
    }
    $x = (float) $v;
    if (!is_finite($x)) {
        return '';
    }
    return number_format($x, $dec, '.', '');
}

function gas_fmt_money($v) {
    if ($v === null || $v === '') {
        return '';
    }
    $x = (float) $v;
    if (!is_finite($x)) {
        return '';
    }
    return number_format($x, 2, '.', '');
}

require_once __DIR__ . '/includes/platinum_stock_gas_url_helpers.php';

require_once __DIR__ . '/includes/platinum_stock_list_sql_include.php';

$sql = $platinum_stock_list_sql . ' LIMIT 5000';

$rows = [];
$load_error = '';
if (!$has_stock) {
    $load_error = 'Stock table not found.';
} else {
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        $load_error = 'Could not load stock: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8');
    } else {
        while ($r = mysqli_fetch_assoc($res)) {
            $rows[] = $r;
        }
        mysqli_free_result($res);
    }
}

// Placeholder thumbnail when stock has no image or primary URL fails (admin/no_image.jpg).
$gas_no_image_src = 'no_image.jpg';
if (!empty($_SERVER['SCRIPT_NAME'])) {
    $gas_sd = str_replace('\\', '/', dirname((string) $_SERVER['SCRIPT_NAME']));
    if ($gas_sd !== '' && $gas_sd !== '/' && $gas_sd !== '.') {
        $gas_no_image_src = rtrim($gas_sd, '/') . '/no_image.jpg';
    }
}
$gas_no_image_src_esc = htmlspecialchars($gas_no_image_src, ENT_QUOTES, 'UTF-8');
$gas_thumb_onerror_attr = ' onerror="this.onerror=null;this.src=' . json_encode($gas_no_image_src, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) . '"';

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

/** @var array<string, array{label: string, keys: string[]}> map column_key -> group meta for two-row header + column picker */
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
/** @var array<string, array{label: string, id: string}> per column key */
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
         WHERE user_id = $gas_user_id AND page_name = 'platinum-stock' AND tab_key = '$gas_tab_esc'
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
    'pageName' => 'platinum-stock',
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

/** Grand totals for footer (additive columns only). */
$gas_grand = [
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
$gas_wastage_per_sum = 0.0;
$gas_wastage_per_cnt = 0;

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Platinum Stock - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include __DIR__ . '/header-script.php'; ?>
</head>
<style>
    :root {
        --gas-navy: #11294b;
        --gas-gold: #c9a227;
    }
    body { margin: 0; overflow-x: hidden; min-height: 100vh; background: #f4f6f9; font-family: Roboto, sans-serif; }
    .gas-wrap { padding: 12px 16px 24px; }
    .gas-head {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }
    .gas-title { font-size: 1.15rem; font-weight: 700; color: var(--gas-navy); margin: 0; }
    .gas-tabs {
        display: inline-flex;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #cfd8e3;
        background: #fff;
    }
    .gas-tabs a {
        padding: 8px 18px;
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--gas-navy);
        text-decoration: none;
        border-right: 1px solid #e2e8f0;
    }
    .gas-tabs a:last-child { border-right: 0; }
    .gas-tabs a:hover { background: #fdf8f0; }
    .gas-tabs a.active {
        background: linear-gradient(180deg, #5b4a9e 0%, #4a3d82 100%);
        color: #fff;
    }
    .gas-table-card {
        background: #fff;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(17, 41, 75, 0.06);
        /* must not clip the column-picker dropdown */
        overflow: visible;
    }
    /* Global style.css forces .table thead th { background: #11294b !important }.
       Our old rule set color to navy — same as background — so labels disappeared. */
    .gas-table.table thead th {
        position: sticky;
        z-index: 4;
        background: #11294b !important;
        color: #ffffff !important;
        border-bottom: 1px solid rgba(201, 162, 39, 0.4) !important;
        font-weight: 700;
        padding: 8px 6px;
        vertical-align: middle;
        text-shadow: none;
    }
    .gas-table.table thead tr.gas-thead-group th {
        top: 0;
        z-index: 5;
        text-align: center;
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #fefce8 !important;
        background: #0d1f3a !important;
        border-bottom: 1px solid rgba(201, 162, 39, 0.5) !important;
        font-weight: 800;
    }
    .gas-table.table thead tr.gas-thead-cols th {
        top: 36px;
        z-index: 4;
        border-bottom: 2px solid var(--gas-gold) !important;
    }
    @media (max-width: 1200px) {
        .gas-table.table thead tr.gas-thead-cols th { top: 34px; }
    }
    .gas-th-draggable { position: relative; user-select: none; cursor: grab; }
    .gas-th-draggable:active { cursor: grabbing; }
    .gas-th-drag-icon {
        display: inline-block;
        vertical-align: middle;
        margin-right: 4px;
        opacity: 0.85;
        pointer-events: none;
        flex-shrink: 0;
        width: 14px;
        height: 14px;
    }
    .gas-th-head-inner {
        display: inline-flex;
        align-items: center;
        max-width: calc(100% - 14px);
        min-width: 0;
    }
    .gas-th-label { pointer-events: none; display: inline-block; min-width: 0; overflow: hidden; text-overflow: ellipsis; }
    .gas-col-resize-handle {
        position: absolute;
        right: 0;
        top: 0;
        bottom: 0;
        width: 8px;
        cursor: col-resize;
        z-index: 6;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12));
    }
    .gas-th-draggable.gas-th-drag-over { outline: 2px dashed var(--gas-gold); outline-offset: -2px; }
    .gas-table-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        padding: 10px 12px;
        background: linear-gradient(180deg, #fdf8f0 0%, #fff 100%);
        border-bottom: 1px solid #e2e8f0;
    }
    .gas-table-card-header h2 {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--gas-navy);
    }
    .gas-table-card-actions {
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .gas-export-btn {
        width: 38px;
        height: 38px;
        padding: 0;
        border-radius: 10px;
        border: 1px solid #d4c4a8;
        background: #fdf8f0;
        color: var(--gas-navy);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s, border-color 0.2s;
    }
    .gas-export-btn:hover {
        background: #f5ecd8;
        border-color: #c9a962;
    }
    .gas-col-picker-wrap {
        position: relative;
        z-index: 1060;
        display: inline-flex;
        align-items: center;
    }
    .gas-col-picker-btn {
        width: 38px;
        height: 38px;
        padding: 0;
        border-radius: 10px;
        border: 1px solid #d4c4a8;
        background: #fdf8f0;
        color: var(--gas-navy);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s, border-color 0.2s;
    }
    .gas-col-picker-btn:hover {
        background: #f5ecd8;
        border-color: #c9a962;
    }
    .gas-col-picker-panel {
        position: absolute;
        right: 0;
        top: calc(100% + 6px);
        z-index: 1070;
        min-width: 280px;
        max-width: min(380px, 94vw);
        max-height: min(70vh, 480px);
        display: flex;
        flex-direction: column;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 12px 40px rgba(17, 41, 75, 0.2);
        overflow: hidden;
    }
    .gas-col-picker-panel.d-none { display: none !important; }
    .gas-col-picker-head {
        flex: 0 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 10px;
        border-bottom: 1px solid #eef2f7;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--gas-navy);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .gas-col-picker-head button {
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        color: #64748b;
        line-height: 1;
    }
    .gas-col-picker-head button:hover { color: var(--gas-navy); }
    .gas-col-picker-search {
        flex: 0 0 auto;
        padding: 8px 10px;
        border-bottom: 1px solid #eef2f7;
    }
    .gas-col-picker-search input {
        width: 100%;
        font-size: 0.8rem;
        padding: 6px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
    .gas-col-picker-list {
        flex: 1 1 auto;
        min-height: 0;
        overflow-x: hidden;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
        padding: 6px 0;
    }
    .gas-col-picker-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        font-size: 0.82rem;
        cursor: pointer;
    }
    .gas-col-picker-item:hover { background: #f8fafc; }
    .gas-col-picker-item input { accent-color: var(--gas-navy); }
    .gas-col-picker-group { border-bottom: 1px solid #f1f5f9; }
    .gas-col-picker-group:last-of-type { border-bottom: 0; }
    .gas-col-picker-group-h {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 10px 4px 12px;
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--gas-navy);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .gas-col-picker-item.gas-col-picker-child { padding-left: 28px; }
    .gas-col-picker-item.gas-col-filter-hide { display: none !important; }
    .gas-col-picker-group.gas-col-filter-hide { display: none !important; }
    .gas-table thead th.gas-col-hidden,
    .gas-table tbody td.gas-col-hidden,
    .gas-table tfoot td.gas-col-hidden {
        display: none !important;
    }
    .gas-table tfoot.gas-tfoot-totals {
        box-shadow: 0 -4px 12px rgba(17, 41, 75, 0.08);
    }
    .gas-table tfoot.gas-tfoot-totals tr {
        background: linear-gradient(180deg, #e8eef6 0%, #f0f4f8 45%, #eef2f9 100%) !important;
    }
    .gas-table tfoot.gas-tfoot-totals td {
        border-top: 2px solid #c5d4e8 !important;
        padding: 10px 6px !important;
        font-size: 0.74rem;
        vertical-align: middle !important;
    }
    .gas-table tfoot.gas-tfoot-totals td.gas-tfoot-label {
        font-weight: 800;
        color: #11294b !important;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        font-size: 0.68rem;
        white-space: nowrap;
    }
    .gas-table tfoot.gas-tfoot-totals td.gas-tfoot-num {
        font-weight: 800;
        color: #1d4ed8 !important;
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
    }
    .gas-table tfoot.gas-tfoot-totals td.gas-tfoot-muted {
        color: #cbd5e1 !important;
        font-weight: 600;
    }
    .gas-table-scroll {
        overflow-x: auto;
        overflow-y: auto;
        max-height: calc(100vh - 260px);
        border-radius: 0 0 10px 10px;
    }
    .gas-table {
        font-size: 0.72rem;
        white-space: nowrap;
        margin: 0;
    }
    .gas-table tbody td {
        padding: 6px;
        vertical-align: middle;
        border-color: #eef2f7;
    }
    .gas-table tbody tr:nth-child(even) { background: #fcfdff; }
    .gas-thumb {
        max-width: 48px;
        max-height: 48px;
        min-width: 36px;
        min-height: 36px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
        background: #f1f5f9;
        vertical-align: middle;
    }
    .gas-img-open-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0;
        margin: 0;
        border: none;
        background: transparent;
        cursor: pointer;
        vertical-align: middle;
        border-radius: 4px;
    }
    .gas-img-open-btn:hover .gas-thumb { border-color: #1a2b4b; box-shadow: 0 0 0 2px rgba(26, 43, 75, 0.15); }
    .gas-img-open-btn:focus { outline: 2px solid #1a2b4b; outline-offset: 2px; }
    #gasAddImageModal { display: none; position: fixed; inset: 0; z-index: 10050; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; padding: 20px; }
    #gasAddImageModal.gas-add-img-show { display: flex; }
    #gasAddImageModal .gas-add-img-box { background: #fff; border-radius: 8px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); width: 100%; max-width: 560px; overflow: hidden; }
    #gasAddImageModal .gas-add-img-header { background: #1a2b4b; color: #fff; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    #gasAddImageModal .gas-add-img-header h5 { margin: 0; font-size: 1.05rem; font-weight: 600; flex: 1; }
    #gasAddImageModal .gas-add-img-header .gas-add-img-bc { font-size: 0.78rem; opacity: 0.85; font-weight: 500; }
    #gasAddImageModal .gas-add-img-close { background: transparent; border: none; color: #fff; font-size: 24px; line-height: 1; cursor: pointer; padding: 0 4px; opacity: 0.9; }
    #gasAddImageModal .gas-add-img-close:hover { opacity: 1; }
    #gasAddImageModal .gas-add-img-body { padding: 20px; }
    #gasAddImageModal .gas-add-img-drop { border: 2px dashed #cbd5e1; border-radius: 8px; min-height: 200px; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px; padding: 16px; background: #f8fafc; cursor: pointer; }
    #gasAddImageModal .gas-add-img-preview-wrap { width: 100%; max-width: 320px; min-height: 160px; display: flex; align-items: center; justify-content: center; }
    #gasAddImageModal .gas-add-img-preview-wrap img { max-width: 100%; max-height: 200px; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0; }
    #gasAddImageModal .gas-add-img-no-preview { color: #94a3b8; font-size: 13px; text-align: center; }
    #gasAddImageModal .gas-add-img-no-preview .gas-add-img-no-ico { font-size: 48px; color: #c4b896; margin-bottom: 8px; }
    #gasAddImageModal .gas-add-img-thumbs { display: flex; flex-wrap: wrap; gap: 8px; width: 100%; justify-content: flex-start; margin-top: 10px; }
    #gasAddImageModal .gas-add-img-thumb { position: relative; width: 64px; height: 64px; flex-shrink: 0; cursor: pointer; border-radius: 6px; border: 2px solid transparent; }
    #gasAddImageModal .gas-add-img-thumb img { width: 100%; height: 100%; object-fit: cover; border-radius: 6px; border: 1px solid #e2e8f0; display: block; }
    #gasAddImageModal .gas-add-img-thumb.gas-add-img-thumb-primary { border-color: #1a2b4b; box-shadow: 0 0 0 2px rgba(26, 43, 75, 0.2); }
    #gasAddImageModal .gas-add-img-thumb-rm { position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; border: none; border-radius: 50%; background: #dc2626; color: #fff; font-size: 12px; line-height: 1; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; z-index: 2; }
    #gasAddImageModal .gas-add-img-thumb-rm:hover { background: #b91c1c; }
    #gasAddImageModal .gas-add-img-browse-wrap { margin-top: 12px; display: flex; justify-content: flex-end; }
    #gasAddImageModal .gas-add-img-browse { width: 56px; height: 56px; border: 2px dashed #cbd5e1; border-radius: 8px; background: #f8fafc; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #64748b; font-size: 24px; }
    #gasAddImageModal .gas-add-img-browse:hover { border-color: #1a2b4b; color: #1a2b4b; background: #e0e7ff; }
    #gasAddImageModal .gas-add-img-hint { font-size: 12px; color: #64748b; font-style: italic; margin-top: 12px; }
    #gasAddImageModal .gas-add-img-footer { padding: 14px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
    #gasAddImageModal .gas-add-img-cam { width: 44px; height: 44px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #475569; font-size: 20px; }
    #gasAddImageModal .gas-add-img-cam:hover { background: #f1f5f9; }
    #gasAddImageModal .gas-add-img-btns { display: flex; gap: 10px; }
    #gasAddImageModal .gas-add-img-cancel, #gasAddImageModal .gas-add-img-save { padding: 8px 20px; border-radius: 6px; font-size: 13px; font-weight: 500; cursor: pointer; border: none; }
    #gasAddImageModal .gas-add-img-cancel { background: #c4b896; color: #fff; }
    #gasAddImageModal .gas-add-img-cancel:hover { background: #b3a385; }
    #gasAddImageModal .gas-add-img-save { background: #c4b896; color: #fff; }
    #gasAddImageModal .gas-add-img-save:hover { background: #b3a385; }
    #gasAddImageModal .gas-add-img-save:disabled { opacity: 0.6; cursor: not-allowed; }
    .gas-alert { padding: 12px 16px; border-radius: 8px; background: #fef2f2; color: #991b1b; font-size: 0.9rem; }
</style>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark">
            <div class="app-brand demo">
                <span class="app-brand-logo demo">
                    <img src="assets/img/logo.png" alt="Brand Logo" class="img-fluid">
                </span>
                <a href="index.php" class="app-brand-text demo sidenav-text font-weight-normal ml-2">AuraGold</a>
                <a href="javascript:" class="layout-sidenav-toggle sidenav-link text-large ml-auto">
                    <i class="ion ion-md-menu align-middle"></i>
                </a>
            </div>
            <div class="sidenav-divider mt-0"></div>
            <ul class="sidenav-inner py-1">
                <li class="sidenav-item">
                    <a href="dashboard.php" class="sidenav-link">
                        <i class="sidenav-icon feather icon-home"></i>
                        <div>Dashboard</div>
                    </a>
                </li>
            </ul>
        </div>

        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar">
                <a href="index.php" class="navbar-brand app-brand demo d-lg-none py-0 mr-4">
                    <span class="app-brand-logo demo"><img src="assets/img/logo-dark.png" alt="" class="img-fluid"></span>
                    <span class="app-brand-text demo font-weight-normal ml-2">AuraGold</span>
                </a>
                <div class="layout-sidenav-toggle navbar-nav d-lg-none align-items-lg-center mr-auto">
                    <a class="nav-item nav-link px-0 mr-lg-4" href="javascript:"><i class="ion ion-md-menu text-large align-middle"></i></a>
                </div>
            </nav>

            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top: 0;">
                    <?php include __DIR__ . '/sidebar.php'; ?>

                    <div class="gas-wrap">
                        <div class="gas-head">
                            <div>
                                <h1 class="gas-title">Platinum Stock</h1>
                            </div>
                        </div>

                        <?php if ($load_error !== '') { ?>
                            <div class="gas-alert"><?php echo $load_error; ?></div>
                        <?php } ?>

                        <div class="gas-table-card">
                            <div class="gas-table-card-header">
                                <h2>Stock list</h2>
                                <div class="gas-table-card-actions">
                                    <button type="button" class="gas-export-btn" id="gasExportExcel" title="Export to Excel">
                                        <i class="feather icon-download" style="width:20px;height:20px;"></i>
                                    </button>
                                    <div class="gas-col-picker-wrap">
                                    <button type="button" class="gas-col-picker-btn" id="gasColPickerToggle" title="Show / hide columns" aria-expanded="false" aria-controls="gasColPickerPanel">
                                        <i class="feather icon-settings" style="width:20px;height:20px;"></i>
                                    </button>
                                    <div class="gas-col-picker-panel d-none" id="gasColPickerPanel" aria-hidden="true">
                                        <div class="gas-col-picker-head">
                                            <span>Columns</span>
                                            <div>
                                                <button type="button" id="gasColPickerReset" title="Show all columns"><i class="feather icon-refresh-cw" style="width:16px;height:16px;"></i></button>
                                                <button type="button" id="gasColPickerClose" title="Close"><i class="feather icon-x" style="width:16px;height:16px;"></i></button>
                                            </div>
                                        </div>
                                        <div class="gas-col-picker-search">
                                            <input type="search" id="gasColPickerFilter" placeholder="Search columns…" autocomplete="off" aria-label="Filter columns">
                                        </div>
                                        <div class="gas-col-picker-list" id="gasColPickerList">
                                            <?php
                                            $gseen = array_fill_keys(array_keys($gas_columns), false);
    foreach ($gas_column_group_defs as $gid => $gdef) {
        $klist = [];
        foreach (array_keys($gas_columns) as $xck) {
            if (in_array($xck, $gdef['keys'], true)) {
                $klist[] = $xck;
            }
        }
        if ($klist === []) {
            continue;
        }
        $gh_id = 'gas_gchk_' . preg_replace('/[^a-z0-9_]/i', '_', $gid);
        $g_all_on = true;
        foreach ($klist as $tk) {
            if (!empty($gas_col_meta[$tk]['hidden'])) {
                $g_all_on = false;
            }
        }
        echo '<div class="gas-col-picker-group" data-gas-ggid="' . htmlspecialchars($gid, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="gas-col-picker-group-h">';
        echo '<input type="checkbox" class="gas-col-group-chk" id="' . htmlspecialchars($gh_id, ENT_QUOTES, 'UTF-8') . '" data-gas-ggid="' . htmlspecialchars($gid, ENT_QUOTES, 'UTF-8') . '"' . ($g_all_on ? ' checked' : '') . '>';
        $glab = (string) $gdef['label'];
        echo '<label for="' . htmlspecialchars($gh_id, ENT_QUOTES, 'UTF-8') . '">' . $glab . '</label>';
        echo '</div>';
        echo '<div class="gas-col-picker-children" data-gas-ggid="' . htmlspecialchars($gid, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($klist as $ck) {
            if (array_key_exists($ck, $gseen)) {
                $gseen[$ck] = true;
            }
            $cid = 'gas_col_' . preg_replace('/[^a-z0-9_]/i', '_', $ck);
            $clab = $gas_columns[$ck] ?? $ck;
            $gp = $gas_col_group_info[$ck]['label_plain'] ?? '';
            echo '<label class="gas-col-picker-item gas-col-picker-child" data-gas-ggid="' . htmlspecialchars($gid, ENT_QUOTES, 'UTF-8') . '" data-gas-col-group-plain="' . htmlspecialchars($gp, ENT_QUOTES, 'UTF-8') . '" data-gas-col-label="' . htmlspecialchars(strtolower($gp . ' ' . (string) $clab), ENT_QUOTES, 'UTF-8') . '">';
            $chk_on = empty($gas_col_meta[$ck]['hidden']);
            echo '<input type="checkbox" class="gas-col-chk" id="' . htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') . '" data-gas-col-key="' . htmlspecialchars($ck, ENT_QUOTES, 'UTF-8') . '" data-gas-ggid="' . htmlspecialchars($gid, ENT_QUOTES, 'UTF-8') . '"' . ($chk_on ? ' checked' : '') . '>';
            echo '<span>' . htmlspecialchars((string) $clab, ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</label>';
        }
        echo '</div></div>';
    }
    $other_ks = [];
    foreach (array_keys($gas_columns) as $ock) {
        if (empty($gseen[$ock])) {
            $other_ks[] = $ock;
        }
    }
    if ($other_ks !== []) {
        $gid = 'other';
        $gh_id = 'gas_gchk_other';
        $g_all_on = true;
        foreach ($other_ks as $tk) {
            if (!empty($gas_col_meta[$tk]['hidden'])) {
                $g_all_on = false;
            }
        }
        echo '<div class="gas-col-picker-group" data-gas-ggid="other">';
        echo '<div class="gas-col-picker-group-h">';
        echo '<input type="checkbox" class="gas-col-group-chk" id="' . htmlspecialchars($gh_id, ENT_QUOTES, 'UTF-8') . '" data-gas-ggid="other"' . ($g_all_on ? ' checked' : '') . '>';
        echo '<label for="' . htmlspecialchars($gh_id, ENT_QUOTES, 'UTF-8') . '">Other</label>';
        echo '</div>';
        echo '<div class="gas-col-picker-children" data-gas-ggid="other">';
        foreach ($other_ks as $ck) {
            $cid = 'gas_col_' . preg_replace('/[^a-z0-9_]/i', '_', $ck);
            $clab = $gas_columns[$ck] ?? $ck;
            $gp = $gas_col_group_info[$ck]['label_plain'] ?? 'Other';
            echo '<label class="gas-col-picker-item gas-col-picker-child" data-gas-ggid="other" data-gas-col-group-plain="' . htmlspecialchars($gp, ENT_QUOTES, 'UTF-8') . '" data-gas-col-label="' . htmlspecialchars(strtolower($gp . ' ' . (string) $clab), ENT_QUOTES, 'UTF-8') . '">';
            $chk_on = empty($gas_col_meta[$ck]['hidden']);
            echo '<input type="checkbox" class="gas-col-chk" id="' . htmlspecialchars($cid, ENT_QUOTES, 'UTF-8') . '" data-gas-col-key="' . htmlspecialchars($ck, ENT_QUOTES, 'UTF-8') . '" data-gas-ggid="other"' . ($chk_on ? ' checked' : '') . '>';
            echo '<span>' . htmlspecialchars((string) $clab, ENT_QUOTES, 'UTF-8') . '</span>';
            echo '</label>';
        }
        echo '</div></div>';
    }
    ?>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div class="gas-table-scroll">
                                <table class="table table-sm table-bordered gas-table" id="gasStockTable" data-gas-visible-count="<?php echo (int) max(1, $gas_visible_count); ?>">
                                    <thead>
                                        <tr class="gas-thead-group">
<?php
foreach ($gas_thead_group_runs as $gr) {
    $gln = (int) ($gr['n'] ?? 1);
    $glt = (string) ($gr['label'] ?? '—');
    echo '                                            <th class="gas-th-group" scope="colgroup" colspan="' . $gln . '">' . $glt . "</th>\n";
}
?>
                                        </tr>
                                        <tr class="gas-thead-cols">
<?php
foreach ($gas_columns as $ck => $clab) {
    $gm = $gas_col_meta[$ck];
    $hcls = !empty($gm['hidden']) ? ' gas-col-hidden' : '';
    $wsty = ($gm['width'] !== null) ? ' style="min-width:' . (int) $gm['width'] . 'px;width:' . (int) $gm['width'] . 'px;max-width:560px;"' : '';
    $ginfo = $gas_col_group_info[$ck] ?? null;
    $gigid = is_array($ginfo) && isset($ginfo['id']) ? (string) $ginfo['id'] : 'other';
    $giglab = is_array($ginfo) && isset($ginfo['label_plain']) ? (string) $ginfo['label_plain'] : 'Other';
    echo '                                            <th class="gas-th-resizable gas-th-draggable' . $hcls . '" draggable="true" data-gas-col="' . htmlspecialchars($ck, ENT_QUOTES, 'UTF-8') . '" data-gas-ggid="' . htmlspecialchars($gigid, ENT_QUOTES, 'UTF-8') . '" data-gas-glabel="' . htmlspecialchars($giglab, ENT_QUOTES, 'UTF-8') . '"' . $wsty
        . '><span class="gas-th-head-inner"><i class="feather icon-move gas-th-drag-icon" aria-hidden="true"></i><span class="gas-th-label">' . htmlspecialchars((string) $clab, ENT_QUOTES, 'UTF-8') . '</span></span><span class="gas-col-resize-handle" title="Drag to resize"></span></th>' . "\n";
}
?>
                                        </tr>
                                    </thead>
                                    <tbody>
<?php
foreach ($rows as $r) {
    $gas_row_barcode = trim((string) ($r['barcode'] ?? ''));
    $gw = $r['sj_gross_weight'];
    if ($gw === null || $gw === '') {
        $gw = $r['opening_weight'] ?? null;
    }
    $nw = $r['sj_net_weight'] ?? null;
    $wt = $r['current_weight'];
    if ($wt === null || (float) $wt <= 0) {
        $wt = $r['final_weight'] ?? null;
    }
    $pw = $r['sj_purity_weight'];
    if ($pw === null || $pw === '') {
        $pw = $r['sj_pure_weight'] ?? null;
    }
    // Older product_opening saves used purity as always-% (net×purity/100) while master opening_purity is often fineness (0–1) or % (>1). Reconcile display using tbl_stock.opening_purity when journal purity is clearly too low.
    $nw_for_purity = $nw;
    if ($nw_for_purity === null || $nw_for_purity === '' || (float) $nw_for_purity <= 0) {
        $nw_for_purity = $wt;
    }
    $voucher_disp = isset($r['voucher_type']) ? trim((string) $r['voucher_type']) : '';
    $op_raw = $r['opening_purity'] ?? null;
    if ($voucher_disp === 'product_opening' && $op_raw !== null && $op_raw !== '' && is_numeric($op_raw) && is_numeric($nw_for_purity) && (float) $nw_for_purity > 0) {
        $opc = (float) $op_raw;
        $p_eff = ($opc > 1) ? ($opc / 100.0) : $opc;
        if ($p_eff > 0 && $p_eff <= 1.001) {
            $pw_exp = (float) $nw_for_purity * $p_eff;
            if ($pw_exp > 0.0001) {
                $pw_f = ($pw !== null && $pw !== '' && is_numeric($pw)) ? (float) $pw : -1.0;
                if ($pw === null || $pw === '' || $pw_exp > $pw_f * 1.5 + 0.0001) {
                    $pw = $pw_exp;
                }
            }
        }
    }
    $carat = $r['pc_carat'] ?? '';
    if ($carat === null || $carat === '') {
        $carat = $r['sj_karat'] ?? '';
    }
    $sjst = isset($r['sj_status']) ? trim((string) $r['sj_status']) : '';
    $sst = isset($r['stock_status']) ? (int) $r['stock_status'] : 0;
    $active_disp = ($sst === 1 ? '1' : '0');
    if ($sjst !== '') {
        $active_disp .= ' / ' . $sjst;
    }
    $barcoded = $r['sj_created_at'] ?? $r['stock_created_at'] ?? '';
    if ($barcoded !== '' && $barcoded !== null) {
        $barcoded = substr((string) $barcoded, 0, 19);
    }
    $imgs_raw = trim((string) ($r['image_urls'] ?? ''));
    $img_cell = '';
    if ($imgs_raw !== '') {
        $first = explode(',', $imgs_raw)[0];
        $first = trim($first);
        if ($first !== '') {
            if (preg_match('#^https?://#i', $first) || strpos($first, '/') === 0) {
                $src = $first;
            } else {
                $src = gas_public_url_for_stored_path($first, $SiteUrl ?? null);
            }
            if (trim($src) !== '') {
                $img_cell = '<img class="gas-thumb" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy"' . $gas_thumb_onerror_attr . '>';
            }
        }
    }
    if ($img_cell === '') {
        $img_cell = '<img class="gas-thumb gas-thumb-placeholder" src="' . $gas_no_image_src_esc . '" alt="" loading="lazy">';
    }
    if (!empty($gas_has_journal_images) && $gas_row_barcode !== '') {
        $img_cell = '<button type="button" class="gas-img-open-btn" data-barcode="' . htmlspecialchars($gas_row_barcode, ENT_QUOTES, 'UTF-8') . '" title="Add or manage images">' . $img_cell . '</button>';
    }
    $info = trim((string) ($r['info_text'] ?? ''));
    $gas_grand['weight'] += is_numeric($wt) ? (float) $wt : 0.0;
    $gas_grand['gross_wt'] += is_numeric($gw) ? (float) $gw : 0.0;
    $gas_grand['purity_wt'] += is_numeric($pw) ? (float) $pw : 0.0;
    $gas_grand['qty'] += is_numeric($r['current_qty'] ?? null) ? (float) $r['current_qty'] : 0.0;
    $gas_grand['stone_wt'] += is_numeric($r['stone_weight'] ?? null) ? (float) $r['stone_weight'] : 0.0;
    $gas_grand['net_wt'] += is_numeric($nw) ? (float) $nw : 0.0;
    $gas_grand['wastage_wt'] += is_numeric($r['wastage_wt'] ?? null) ? (float) $r['wastage_wt'] : 0.0;
    $wpp_row = $r['wastage_per'] ?? null;
    if ($wpp_row !== null && $wpp_row !== '' && is_numeric($wpp_row)) {
        $gas_wastage_per_sum += (float) $wpp_row;
        $gas_wastage_per_cnt++;
    }
    $gas_grand['metal_cost'] += is_numeric($r['metal_cost'] ?? null) ? (float) $r['metal_cost'] : 0.0;
    $gas_grand['making_cost'] += is_numeric($r['making_cost'] ?? null) ? (float) $r['making_cost'] : 0.0;
    $gas_grand['making_charge_amt'] += is_numeric($r['making_amount'] ?? null) ? (float) $r['making_amount'] : 0.0;
    $gas_grand['stone_cost'] += is_numeric($r['stone_cost'] ?? null) ? (float) $r['stone_cost'] : 0.0;
    $gas_grand['purchase_amount'] += is_numeric($r['purchase_amount'] ?? null) ? (float) $r['purchase_amount'] : 0.0;
    $gas_grand['metal_value'] += is_numeric($r['metal_value'] ?? null) ? (float) $r['metal_value'] : 0.0;
    $gas_grand['stone_amt'] += is_numeric($r['stone_amount'] ?? null) ? (float) $r['stone_amount'] : 0.0;

    $gas_row_cells = [
        'imageUrls' => $img_cell,
        'info' => htmlspecialchars($info, ENT_QUOTES, 'UTF-8'),
        'huid' => htmlspecialchars((string) ($r['huid_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'barcode' => htmlspecialchars((string) ($r['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'product_name' => htmlspecialchars((string) ($r['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'location' => htmlspecialchars((string) ($r['sj_location'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'weight' => htmlspecialchars(gas_fmt_num($wt, 3), ENT_QUOTES, 'UTF-8'),
        'gross_wt' => htmlspecialchars(gas_fmt_num($gw, 3), ENT_QUOTES, 'UTF-8'),
        'purity_wt' => htmlspecialchars(gas_fmt_num($pw, 3), ENT_QUOTES, 'UTF-8'),
        'qty' => htmlspecialchars(gas_fmt_num($r['current_qty'] ?? null, 2), ENT_QUOTES, 'UTF-8'),
        'carat' => htmlspecialchars((string) $carat, ENT_QUOTES, 'UTF-8'),
        'active' => htmlspecialchars($active_disp, ENT_QUOTES, 'UTF-8'),
        'voucher_type' => htmlspecialchars((string) ($r['voucher_type'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'invoice_no' => htmlspecialchars((string) ($r['invoice_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'supplier_name' => htmlspecialchars((string) ($r['supplier_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'category' => htmlspecialchars((string) ($r['category_display'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'article' => htmlspecialchars((string) ($r['article'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'metal_cost' => htmlspecialchars(gas_fmt_money($r['metal_cost'] ?? null), ENT_QUOTES, 'UTF-8'),
        'making_cost' => htmlspecialchars(gas_fmt_money($r['making_cost'] ?? null), ENT_QUOTES, 'UTF-8'),
        'stone_wt' => htmlspecialchars(gas_fmt_num($r['stone_weight'] ?? null, 3), ENT_QUOTES, 'UTF-8'),
        'net_wt' => htmlspecialchars(gas_fmt_num($nw, 3), ENT_QUOTES, 'UTF-8'),
        'barcoded_date' => htmlspecialchars((string) $barcoded, ENT_QUOTES, 'UTF-8'),
        'making_charge_amt' => htmlspecialchars(gas_fmt_money($r['making_amount'] ?? null), ENT_QUOTES, 'UTF-8'),
        'stone_cost' => htmlspecialchars(gas_fmt_money($r['stone_cost'] ?? null), ENT_QUOTES, 'UTF-8'),
        'purchase_amount' => htmlspecialchars(gas_fmt_money($r['purchase_amount'] ?? null), ENT_QUOTES, 'UTF-8'),
        'making_type' => htmlspecialchars((string) ($r['making_type'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'metal_value' => htmlspecialchars(gas_fmt_money($r['metal_value'] ?? null), ENT_QUOTES, 'UTF-8'),
        'stone_rate' => htmlspecialchars(gas_fmt_money($r['stone_rate'] ?? null), ENT_QUOTES, 'UTF-8'),
        'stone_charge_type' => htmlspecialchars((string) ($r['stone_charge_type'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'stone_amt' => htmlspecialchars(gas_fmt_money($r['stone_amount'] ?? null), ENT_QUOTES, 'UTF-8'),
        'making_charge_rate' => htmlspecialchars(gas_fmt_money($r['making_rate'] ?? null), ENT_QUOTES, 'UTF-8'),
        'wastage_wt' => htmlspecialchars(gas_fmt_num($r['wastage_wt'] ?? null, 3), ENT_QUOTES, 'UTF-8'),
        'wastage_per' => htmlspecialchars(gas_fmt_num($r['wastage_per'] ?? null, 2), ENT_QUOTES, 'UTF-8'),
    ];
    echo '<tr data-gas-barcode="' . htmlspecialchars($gas_row_barcode, ENT_QUOTES, 'UTF-8') . '">';
    foreach ($gas_columns as $gck => $_glab) {
        $gm = $gas_col_meta[$gck];
        $hcls = !empty($gm['hidden']) ? ' gas-col-hidden' : '';
        $wsty = ($gm['width'] !== null) ? ' style="min-width:' . (int) $gm['width'] . 'px;width:' . (int) $gm['width'] . 'px;max-width:560px;"' : '';
        $inner = $gas_row_cells[$gck] ?? '';
        echo '<td data-gas-col="' . htmlspecialchars($gck, ENT_QUOTES, 'UTF-8') . '" class="gas-td-cell' . $hcls . '"' . $wsty . '>' . $inner . '</td>';
    }
    echo "</tr>\n";
}
if (count($rows) === 0 && $load_error === '') {
    echo '<tr><td colspan="' . (int) max(1, $gas_visible_count) . '" class="text-center text-muted py-4 gas-empty-row" data-gas-empty="1">No rows to show.</td></tr>';
}

$gas_wastage_per_avg = ($gas_wastage_per_cnt > 0) ? ($gas_wastage_per_sum / $gas_wastage_per_cnt) : null;
?>
                                    </tbody>
                                    <tfoot class="gas-tfoot-totals">
                                        <tr>
<?php
foreach ($gas_columns as $ck => $clab) {
    $cls = 'gas-tfoot-cell';
    $inner = '';
    if ($ck === 'imageUrls') {
        $inner = 'Grand Total';
        $cls .= ' gas-tfoot-label';
    } elseif ($ck === 'weight') {
        $inner = gas_fmt_num($gas_grand['weight'], 3);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'gross_wt') {
        $inner = gas_fmt_num($gas_grand['gross_wt'], 3);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'purity_wt') {
        $inner = gas_fmt_num($gas_grand['purity_wt'], 3);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'qty') {
        $inner = gas_fmt_num($gas_grand['qty'], 2);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'stone_wt') {
        $inner = gas_fmt_num($gas_grand['stone_wt'], 3);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'net_wt') {
        $inner = gas_fmt_num($gas_grand['net_wt'], 3);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'wastage_wt') {
        $inner = gas_fmt_num($gas_grand['wastage_wt'], 3);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'wastage_per') {
        $inner = $gas_wastage_per_avg !== null ? gas_fmt_num($gas_wastage_per_avg, 2) : '';
        $cls .= $inner !== '' ? ' gas-tfoot-num' : ' gas-tfoot-muted';
    } elseif ($ck === 'metal_cost') {
        $inner = gas_fmt_money($gas_grand['metal_cost']);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'making_cost') {
        $inner = gas_fmt_money($gas_grand['making_cost']);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'making_charge_amt') {
        $inner = gas_fmt_money($gas_grand['making_charge_amt']);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'stone_cost') {
        $inner = gas_fmt_money($gas_grand['stone_cost']);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'purchase_amount') {
        $inner = gas_fmt_money($gas_grand['purchase_amount']);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'metal_value') {
        $inner = gas_fmt_money($gas_grand['metal_value']);
        $cls .= ' gas-tfoot-num';
    } elseif ($ck === 'stone_amt') {
        $inner = gas_fmt_money($gas_grand['stone_amt']);
        $cls .= ' gas-tfoot-num';
    } elseif (in_array($ck, ['stone_rate', 'making_charge_rate'], true)) {
        $inner = '—';
        $cls .= ' gas-tfoot-muted';
    } else {
        $cls .= ' gas-tfoot-muted';
        $inner = '';
    }
    $gm = $gas_col_meta[$ck];
    $hcls = !empty($gm['hidden']) ? ' gas-col-hidden' : '';
    $wsty = ($gm['width'] !== null) ? ' style="min-width:' . (int) $gm['width'] . 'px;width:' . (int) $gm['width'] . 'px;max-width:560px;"' : '';
    echo '<td data-gas-col="' . htmlspecialchars($ck, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($cls . $hcls, ENT_QUOTES, 'UTF-8') . '"' . $wsty . '>' . htmlspecialchars($inner, ENT_QUOTES, 'UTF-8') . '</td>';
}
?>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($gas_has_journal_images)) : ?>
<div id="gasAddImageModal" aria-hidden="true">
    <div class="gas-add-img-box" role="dialog" aria-labelledby="gasAddImageTitle" aria-modal="true">
        <div class="gas-add-img-header">
            <h5 id="gasAddImageTitle">Add Image</h5>
            <span class="gas-add-img-bc" id="gasAddImageBarcodeLabel"></span>
            <button type="button" class="gas-add-img-close" id="gasAddImageClose" title="Close">&times;</button>
        </div>
        <div class="gas-add-img-body">
            <div class="gas-add-img-drop" id="gasAddImageDrop" title="Click to add images">
                <div class="gas-add-img-preview-wrap" id="gasAddImagePreviewWrap">
                    <div class="gas-add-img-no-preview" id="gasAddImageNoPreview">
                        <div class="gas-add-img-no-ico"><i class="feather icon-image"></i></div>
                        <div>NO PREVIEW AVAILABLE</div>
                    </div>
                    <img id="gasAddImagePreviewImg" src="" alt="" style="display:none;">
                </div>
                <div class="gas-add-img-thumbs" id="gasAddImageThumbs"></div>
            </div>
            <div class="gas-add-img-browse-wrap">
                <div class="gas-add-img-browse" id="gasAddImageBrowse" title="Browse for images"><i class="feather icon-upload"></i></div>
            </div>
            <input type="file" id="gasAddImageFileInput" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple style="display:none;">
            <input type="file" id="gasAddImageCameraInput" accept="image/*" capture="environment" style="display:none;">
            <p class="gas-add-img-hint">Click the upload area or use the camera below to add images. Click a thumbnail to set as primary.</p>
        </div>
        <div class="gas-add-img-footer">
            <button type="button" class="gas-add-img-cam" id="gasAddImageCameraBtn" title="Capture from camera"><i class="feather icon-camera"></i></button>
            <div class="gas-add-img-btns">
                <button type="button" class="gas-add-img-cancel" id="gasAddImageCancel">CANCEL</button>
                <button type="button" class="gas-add-img-save" id="gasAddImageSave">SAVE</button>
            </div>
        </div>
    </div>
</div>
<script>
window.GAS_BARCODE_IMAGE_CFG = <?php echo json_encode([
    'listUrl' => 'ajax/gas-list-stock-journal-images.php',
    'uploadUrl' => 'ajax/upload-stock-journal-images.php',
    'deleteUrl' => 'ajax/delete-stock-journal-image.php',
    'primaryUrl' => 'ajax/gas-set-primary-stock-journal-image.php',
    'noImageSrc' => $gas_no_image_src,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<?php endif; ?>
<?php include __DIR__ . '/footer-script.php'; ?>
<script>
(function () {
    var btn = document.getElementById('gasExportExcel');
    if (!btn) {
        return;
    }
    btn.addEventListener('click', function () {
        window.location.href = 'ajax/export-platinum-stock-excel.php';
    });
})();
</script>
<script>
window.GAS_COL_PREFS = <?php echo json_encode($gas_js_prefs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
(function () {
    var tbl = document.getElementById('gasStockTable');
    if (!tbl) return;

    var saveTimer = null;
    var prefs = window.GAS_COL_PREFS || {};
    var ajaxUrl = 'ajax/save-product-modal-column-preferences.php';

    function gasVisibleColCount() {
        return tbl.querySelectorAll('thead th[data-gas-col]:not(.gas-col-hidden)').length;
    }

    function gasSyncEmptyColspan() {
        var empty = tbl.querySelector('td[data-gas-empty]');
        if (empty) {
            empty.colSpan = Math.max(1, gasVisibleColCount());
        }
        tbl.setAttribute('data-gas-visible-count', String(Math.max(1, gasVisibleColCount())));
    }

    function gasCollectOrderKeys() {
        var out = [];
        tbl.querySelectorAll('thead th[data-gas-col]').forEach(function (th) {
            out.push(th.getAttribute('data-gas-col'));
        });
        return out;
    }

    function gasCollectPreferences() {
        var o = {};
        document.querySelectorAll('.gas-col-chk').forEach(function (c) {
            var k = c.getAttribute('data-gas-col-key');
            if (k) o[k] = c.checked ? 1 : 0;
        });
        return o;
    }

    function gasCollectWidths() {
        var o = {};
        tbl.querySelectorAll('thead th[data-gas-col]').forEach(function (th) {
            var k = th.getAttribute('data-gas-col');
            var w = parseInt(th.style.width, 10);
            if (isNaN(w) || w < 40) {
                w = Math.round(th.getBoundingClientRect().width);
            }
            if (!isNaN(w) && w >= 40) o[k] = w;
        });
        return o;
    }

    function gasApplyColumnWidth(key, px) {
        px = Math.max(40, Math.min(1200, Math.round(px)));
        tbl.querySelectorAll('[data-gas-col]').forEach(function (el) {
            if (el.getAttribute('data-gas-col') === key) {
                el.style.minWidth = px + 'px';
                el.style.width = px + 'px';
                el.style.maxWidth = '560px';
            }
        });
    }

    function gasSaveState() {
        if (!prefs.userId || prefs.userId <= 0) return;
        var fd = new FormData();
        fd.append('page_name', prefs.pageName || 'platinum-stock');
        fd.append('tab_key', prefs.tabKey || '');
        fd.append('order_keys', JSON.stringify(gasCollectOrderKeys()));
        fd.append('preferences', JSON.stringify(gasCollectPreferences()));
        fd.append('widths', JSON.stringify(gasCollectWidths()));
        fetch(ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .catch(function () { /* ignore */ });
    }

    function gasSaveDebounced() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(gasSaveState, 450);
    }

    function gasReflowGroupRow() {
        var gRow = tbl.querySelector('tr.gas-thead-group');
        var cRow = tbl.querySelector('tr.gas-thead-cols');
        if (!gRow || !cRow) return;
        gRow.innerHTML = '';
        var ths = cRow.querySelectorAll('th[data-gas-col]');
        var vis = [];
        ths.forEach(function (th) {
            if (!th.classList.contains('gas-col-hidden')) {
                vis.push(th);
            }
        });
        if (vis.length === 0) {
            if (ths.length) {
                var fb = document.createElement('th');
                fb.className = 'gas-th-group';
                fb.setAttribute('scope', 'colgroup');
                fb.setAttribute('colspan', String(ths.length));
                fb.textContent = '—';
                gRow.appendChild(fb);
            }
            return;
        }
        var i = 0;
        while (i < vis.length) {
            var th0 = vis[i];
            var gg = th0.getAttribute('data-gas-ggid') || 'other';
            var lab = th0.getAttribute('data-gas-glabel') || 'Other';
            var n = 1;
            var j;
            for (j = i + 1; j < vis.length; j++) {
                if ((vis[j].getAttribute('data-gas-ggid') || 'other') !== gg) {
                    break;
                }
                n++;
            }
            var oth = document.createElement('th');
            oth.className = 'gas-th-group';
            oth.setAttribute('scope', 'colgroup');
            oth.setAttribute('colspan', String(n));
            oth.setAttribute('data-gas-ggid', gg);
            oth.textContent = lab;
            gRow.appendChild(oth);
            i += n;
        }
    }

    function gasUpdateAllGroupChks() {
        document.querySelectorAll('.gas-col-picker-group .gas-col-group-chk').forEach(function (h) {
            var grp = h.closest('.gas-col-picker-group');
            if (!grp) return;
            var kids = grp.querySelectorAll('.gas-col-chk[data-gas-col-key]');
            var n = 0;
            var on = 0;
            kids.forEach(function (c) {
                n += 1;
                if (c.checked) on += 1;
            });
            h.indeterminate = n > 0 && on > 0 && on < n;
            h.checked = n > 0 && on === n;
        });
    }

    function gasApplyColumnVisibilityFromChecks() {
        var map = {};
        document.querySelectorAll('.gas-col-chk').forEach(function (c) {
            var k = c.getAttribute('data-gas-col-key');
            if (k) map[k] = c.checked;
        });
        tbl.querySelectorAll('thead th[data-gas-col], tbody td[data-gas-col], tfoot td[data-gas-col]').forEach(function (el) {
            var k = el.getAttribute('data-gas-col');
            if (!k) return;
            if (map[k] === false) el.classList.add('gas-col-hidden');
            else el.classList.remove('gas-col-hidden');
        });
        gasSyncEmptyColspan();
        gasReflowGroupRow();
        gasUpdateAllGroupChks();
    }

    function gasByColKey(row, key) {
        var f = null;
        if (!row) return null;
        row.querySelectorAll('[data-gas-col]').forEach(function (el) {
            if (el.getAttribute('data-gas-col') === key) {
                f = el;
            }
        });
        return f;
    }

    function gasMoveColumnBefore(movedKey, targetKey) {
        if (!movedKey || !targetKey || movedKey === targetKey) return;
        var theadRow = tbl.querySelector('tr.gas-thead-cols') || tbl.querySelector('thead tr');
        if (!theadRow) return;
        var thM = gasByColKey(theadRow, movedKey);
        var thT = gasByColKey(theadRow, targetKey);
        if (!thM || !thT) return;
        thT.parentNode.insertBefore(thM, thT);
        tbl.querySelectorAll('tbody tr').forEach(function (tr) {
            if (tr.querySelector('td[data-gas-empty]')) return;
            var a = gasByColKey(tr, movedKey);
            var b = gasByColKey(tr, targetKey);
            if (a && b) b.parentNode.insertBefore(a, b);
        });
        var ftr = tbl.querySelector('tfoot tr');
        if (ftr) {
            var a = gasByColKey(ftr, movedKey);
            var b = gasByColKey(ftr, targetKey);
            if (a && b) b.parentNode.insertBefore(a, b);
        }
        gasReflowGroupRow();
        gasSyncPickerOrder();
        gasSaveDebounced();
    }

    function gasSyncPickerOrder() {
        var list = document.getElementById('gasColPickerList');
        if (!list) return;
        gasCollectOrderKeys().forEach(function (k) {
            if (!k) return;
            var found = null;
            list.querySelectorAll('input.gas-col-chk[data-gas-col-key]').forEach(function (inp) {
                if (inp.getAttribute('data-gas-col-key') === k) {
                    found = inp;
                }
            });
            if (found) {
                var it = found.closest('.gas-col-picker-item');
                var p = it ? it.parentNode : null;
                if (p && p.classList && p.classList.contains('gas-col-picker-children') && it) {
                    p.appendChild(it);
                }
            }
        });
        gasUpdateAllGroupChks();
    }

    var dragKey = null;
    tbl.querySelectorAll('thead th.gas-th-draggable').forEach(function (th) {
        th.addEventListener('dragstart', function (e) {
            if (e.target && e.target.closest && e.target.closest('.gas-col-resize-handle')) {
                e.preventDefault();
                return;
            }
            dragKey = th.getAttribute('data-gas-col');
            try {
                e.dataTransfer.setData('text/plain', dragKey);
                e.dataTransfer.effectAllowed = 'move';
            } catch (err) {}
            th.style.opacity = '0.65';
        });
        th.addEventListener('dragend', function () {
            th.style.opacity = '';
            tbl.querySelectorAll('.gas-th-drag-over').forEach(function (x) { x.classList.remove('gas-th-drag-over'); });
        });
        th.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            th.classList.add('gas-th-drag-over');
        });
        th.addEventListener('dragleave', function () {
            th.classList.remove('gas-th-drag-over');
        });
        th.addEventListener('drop', function (e) {
            e.preventDefault();
            th.classList.remove('gas-th-drag-over');
            var from = dragKey;
            try {
                from = e.dataTransfer.getData('text/plain') || from;
            } catch (err2) {}
            var to = th.getAttribute('data-gas-col');
            if (from && to) gasMoveColumnBefore(from, to);
            dragKey = null;
        });
    });

    tbl.querySelectorAll('.gas-col-resize-handle').forEach(function (handle) {
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var th = handle.closest('th');
            if (!th) return;
            var colKey = th.getAttribute('data-gas-col');
            var startX = e.clientX;
            var startW = th.offsetWidth;
            function mm(ev) {
                var nw = Math.max(40, Math.min(1200, startW + (ev.clientX - startX)));
                gasApplyColumnWidth(colKey, nw);
            }
            function mu() {
                document.removeEventListener('mousemove', mm);
                document.removeEventListener('mouseup', mu);
                gasSaveDebounced();
            }
            document.addEventListener('mousemove', mm);
            document.addEventListener('mouseup', mu);
        });
    });

    var toggle = document.getElementById('gasColPickerToggle');
    var panel = document.getElementById('gasColPickerPanel');
    var closeBtn = document.getElementById('gasColPickerClose');
    var resetBtn = document.getElementById('gasColPickerReset');
    var filterInp = document.getElementById('gasColPickerFilter');

    function gasClosePanel() {
        if (panel) {
            panel.classList.add('d-none');
            panel.setAttribute('aria-hidden', 'true');
        }
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }

    function gasTogglePanel() {
        if (!panel || !toggle) return;
        var open = panel.classList.contains('d-none');
        if (open) {
            panel.classList.remove('d-none');
            panel.setAttribute('aria-hidden', 'false');
            toggle.setAttribute('aria-expanded', 'true');
            gasSyncPickerOrder();
            if (filterInp) {
                filterInp.value = '';
                filterInp.dispatchEvent(new Event('input', { bubbles: true }));
            }
        } else {
            gasClosePanel();
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            gasTogglePanel();
        });
    }
    if (closeBtn) closeBtn.addEventListener('click', function (e) { e.preventDefault(); gasClosePanel(); });
    if (resetBtn) {
        resetBtn.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.gas-col-chk').forEach(function (c) { c.checked = true; });
            document.querySelectorAll('.gas-col-group-chk').forEach(function (h) {
                h.checked = true;
                h.indeterminate = false;
            });
            gasApplyColumnVisibilityFromChecks();
            gasSaveDebounced();
        });
    }
    document.addEventListener('click', function (e) {
        if (!panel || panel.classList.contains('d-none')) return;
        var w = document.querySelector('.gas-col-picker-wrap');
        if (w && !w.contains(e.target)) gasClosePanel();
    });

    document.querySelectorAll('.gas-col-chk').forEach(function (chk) {
        chk.addEventListener('change', function () {
            gasApplyColumnVisibilityFromChecks();
            gasSaveDebounced();
        });
    });
    document.querySelectorAll('.gas-col-group-chk').forEach(function (h) {
        h.addEventListener('change', function () {
            h.indeterminate = false;
            var grp = h.closest('.gas-col-picker-group');
            if (!grp) return;
            var on = h.checked;
            grp.querySelectorAll('.gas-col-chk').forEach(function (c) {
                c.checked = on;
            });
            gasApplyColumnVisibilityFromChecks();
            gasSaveDebounced();
        });
    });

    if (filterInp) {
        filterInp.addEventListener('input', function () {
            var q = (filterInp.value || '').trim().toLowerCase();
            document.querySelectorAll('#gasColPickerList .gas-col-picker-item.gas-col-picker-child').forEach(function (row) {
                var lab = (row.getAttribute('data-gas-col-label') || '');
                if (!q || lab.indexOf(q) !== -1) {
                    row.classList.remove('gas-col-filter-hide');
                } else {
                    row.classList.add('gas-col-filter-hide');
                }
            });
            document.querySelectorAll('#gasColPickerList .gas-col-picker-group').forEach(function (grp) {
                if (!q) {
                    grp.classList.remove('gas-col-filter-hide');
                    return;
                }
                var any = false;
                grp.querySelectorAll('.gas-col-picker-item.gas-col-picker-child').forEach(function (row) {
                    if (!row.classList.contains('gas-col-filter-hide')) {
                        any = true;
                    }
                });
                if (any) {
                    grp.classList.remove('gas-col-filter-hide');
                } else {
                    grp.classList.add('gas-col-filter-hide');
                }
            });
        });
    }

    gasReflowGroupRow();
    gasUpdateAllGroupChks();
    gasSyncEmptyColspan();
})();
</script>
<?php if (!empty($gas_has_journal_images)) : ?>
<script>
(function () {
    var cfg = window.GAS_BARCODE_IMAGE_CFG;
    var modal = document.getElementById('gasAddImageModal');
    var tbl = document.getElementById('gasStockTable');
    if (!cfg || !modal || !tbl) return;

    var elBc = document.getElementById('gasAddImageBarcodeLabel');
    var elNoPrev = document.getElementById('gasAddImageNoPreview');
    var elPrevImg = document.getElementById('gasAddImagePreviewImg');
    var elThumbs = document.getElementById('gasAddImageThumbs');
    var elFile = document.getElementById('gasAddImageFileInput');
    var elCam = document.getElementById('gasAddImageCameraInput');
    var elBrowse = document.getElementById('gasAddImageBrowse');
    var elDrop = document.getElementById('gasAddImageDrop');
    var elSave = document.getElementById('gasAddImageSave');
    var elClose = document.getElementById('gasAddImageClose');
    var elCancel = document.getElementById('gasAddImageCancel');
    var elCamBtn = document.getElementById('gasAddImageCameraBtn');

    var state = { barcode: '', existing: [], pending: [] };

    function showModal() {
        modal.classList.add('gas-add-img-show');
        modal.setAttribute('aria-hidden', 'false');
    }
    function hideModal() {
        if (elThumbs) {
            elThumbs.querySelectorAll('img').forEach(function (im) {
                if (im.src && im.src.indexOf('blob:') === 0) {
                    try { (window.URL || window.webkitURL).revokeObjectURL(im.src); } catch (e1) {}
                }
            });
            elThumbs.innerHTML = '';
        }
        if (elPrevImg && elPrevImg.src && elPrevImg.src.indexOf('blob:') === 0) {
            try { (window.URL || window.webkitURL).revokeObjectURL(elPrevImg.src); } catch (e2) {}
        }
        modal.classList.remove('gas-add-img-show');
        modal.setAttribute('aria-hidden', 'true');
        state.barcode = '';
        state.existing = [];
        state.pending = [];
        if (elFile) elFile.value = '';
        if (elCam) elCam.value = '';
        gasRenderPreview();
    }

    function gasRenderPreview() {
        if (!elNoPrev || !elPrevImg) return;
        var primaryUrl = '';
        if (state.existing.length > 0) {
            primaryUrl = state.existing[0].url || '';
        } else if (state.pending.length > 0) {
            try {
                primaryUrl = (window.URL || window.webkitURL).createObjectURL(state.pending[0]);
            } catch (e) {
                primaryUrl = '';
            }
        }
        if (primaryUrl) {
            elNoPrev.style.display = 'none';
            elPrevImg.style.display = '';
            if (elPrevImg.src && elPrevImg.src.indexOf('blob:') === 0) {
                try {
                    (window.URL || window.webkitURL).revokeObjectURL(elPrevImg.src);
                } catch (eRev) {}
            }
            elPrevImg.src = primaryUrl;
        } else {
            elNoPrev.style.display = '';
            elPrevImg.style.display = 'none';
            elPrevImg.removeAttribute('src');
        }
    }

    function gasRenderThumbs() {
        if (!elThumbs) return;
        elThumbs.querySelectorAll('img[src^="blob:"]').forEach(function (im) {
            try {
                (window.URL || window.webkitURL).revokeObjectURL(im.src);
            } catch (e0) {}
        });
        elThumbs.innerHTML = '';
        state.existing.forEach(function (img, ix) {
            var wrap = document.createElement('div');
            wrap.className = 'gas-add-img-thumb' + (ix === 0 ? ' gas-add-img-thumb-primary' : '');
            wrap.setAttribute('data-server-id', String(img.id));
            var im = document.createElement('img');
            im.src = img.url;
            im.alt = '';
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'gas-add-img-thumb-rm';
            rm.setAttribute('data-server-id', String(img.id));
            rm.innerHTML = '\u00d7';
            rm.title = 'Remove';
            wrap.appendChild(im);
            wrap.appendChild(rm);
            elThumbs.appendChild(wrap);
        });
        state.pending.forEach(function (file, ix) {
            var wrap = document.createElement('div');
            var isPri = (state.existing.length === 0 && ix === 0);
            wrap.className = 'gas-add-img-thumb' + (isPri ? ' gas-add-img-thumb-primary' : '');
            wrap.setAttribute('data-pending-idx', String(ix));
            var im = document.createElement('img');
            try {
                im.src = (window.URL || window.webkitURL).createObjectURL(file);
            } catch (e) {
                im.src = '';
            }
            im.alt = '';
            var rm = document.createElement('button');
            rm.type = 'button';
            rm.className = 'gas-add-img-thumb-rm';
            rm.setAttribute('data-pending-idx', String(ix));
            rm.innerHTML = '\u00d7';
            rm.title = 'Remove';
            wrap.appendChild(im);
            wrap.appendChild(rm);
            elThumbs.appendChild(wrap);
        });
        gasRenderPreview();
    }

    function gasUpdateTableRowThumb(barcode, url) {
        var rows = tbl.querySelectorAll('tr[data-gas-barcode]');
        var bc = String(barcode || '').trim();
        for (var i = 0; i < rows.length; i++) {
            if (String(rows[i].getAttribute('data-gas-barcode') || '').trim() !== bc) continue;
            var cell = rows[i].querySelector('td[data-gas-col="imageUrls"]');
            var img = null;
            if (cell) {
                img = cell.querySelector('img.gas-thumb') || cell.querySelector('img');
            }
            if (!img) {
                var btn = rows[i].querySelector('.gas-img-open-btn');
                img = btn ? btn.querySelector('img') : null;
            }
            if (!img) break;
            var useUrl = url != null ? String(url).trim() : '';
            if (!useUrl || useUrl === 'undefined') {
                img.classList.add('gas-thumb-placeholder');
                img.src = cfg.noImageSrc || '';
            } else {
                img.classList.remove('gas-thumb-placeholder');
                img.src = useUrl;
            }
            break;
        }
    }

    function gasReloadList(cb) {
        if (!state.barcode) {
            if (cb) cb();
            return;
        }
        var fd = new FormData();
        fd.append('barcode_no', state.barcode);
        fetch(cfg.listUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (text) {
                var d;
                try {
                    d = JSON.parse(text);
                } catch (eJ) {
                    d = { status: 'error', images: [] };
                }
                state.existing = (d.status === 'success' && Array.isArray(d.images)) ? d.images : [];
                gasRenderThumbs();
                if (cb) cb();
            })
            .catch(function () {
                state.existing = [];
                gasRenderThumbs();
                if (cb) cb();
            });
    }

    function gasOpen(barcode) {
        state.barcode = (barcode || '').trim();
        state.pending = [];
        if (elBc) elBc.textContent = state.barcode;
        if (elFile) elFile.value = '';
        if (elCam) elCam.value = '';
        showModal();
        gasReloadList();
    }

    function gasDeleteServer(id) {
        if (!window.confirm('Delete this image?')) return;
        var fd = new FormData();
        fd.append('id', String(id));
        fetch(cfg.deleteUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.status === 'success') {
                    gasReloadList(function () {
                        gasUpdateTableRowThumb(state.barcode, state.existing[0] ? state.existing[0].url : null);
                    });
                } else {
                    window.alert(d.message || 'Delete failed');
                }
            })
            .catch(function () {
                window.alert('Delete failed');
            });
    }

    function gasSetPrimary(id) {
        if (state.existing.length && state.existing[0].id === id) return;
        var fd = new FormData();
        fd.append('barcode_no', state.barcode);
        fd.append('image_id', String(id));
        fetch(cfg.primaryUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.status === 'success') {
                    gasReloadList(function () {
                        gasUpdateTableRowThumb(state.barcode, state.existing[0] ? state.existing[0].url : null);
                    });
                } else {
                    window.alert(d.message || 'Could not set primary');
                }
            })
            .catch(function () {
                window.alert('Could not set primary');
            });
    }

    function gasAddPendingFiles(files) {
        if (!files || !files.length) return;
        var allowedMime = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/pjpeg'];
        function okExt(n) {
            return /\.(jpe?g|png|webp)$/i.test(n || '');
        }
        for (var i = 0; i < files.length; i++) {
            var f = files[i];
            if (!f) continue;
            var t = (f.type || '').toLowerCase();
            if (allowedMime.indexOf(t) !== -1 || (t === '' && okExt(f.name)) || (t === 'application/octet-stream' && okExt(f.name))) {
                state.pending.push(f);
            }
        }
        gasRenderThumbs();
    }

    tbl.addEventListener('click', function (e) {
        var btn = e.target.closest('.gas-img-open-btn');
        if (!btn) return;
        e.preventDefault();
        var bc = btn.getAttribute('data-barcode') || '';
        if (bc) gasOpen(bc);
    });

    if (elThumbs) {
        elThumbs.addEventListener('click', function (e) {
            var rm = e.target.closest('.gas-add-img-thumb-rm');
            if (rm) {
                e.stopPropagation();
                e.preventDefault();
                var sid = rm.getAttribute('data-server-id');
                var pidx = rm.getAttribute('data-pending-idx');
                if (sid) {
                    gasDeleteServer(parseInt(sid, 10));
                } else if (pidx !== null && pidx !== '') {
                    var pi = parseInt(pidx, 10);
                    if (!isNaN(pi) && pi >= 0 && pi < state.pending.length) {
                        state.pending.splice(pi, 1);
                        gasRenderThumbs();
                    }
                }
                return;
            }
            var th = e.target.closest('.gas-add-img-thumb');
            if (!th) return;
            var sid2 = th.getAttribute('data-server-id');
            var pidx2 = th.getAttribute('data-pending-idx');
            if (sid2) {
                gasSetPrimary(parseInt(sid2, 10));
            } else if (pidx2 !== null && pidx2 !== '') {
                var j = parseInt(pidx2, 10);
                if (j > 0 && j < state.pending.length) {
                    var moved = state.pending.splice(j, 1)[0];
                    state.pending.unshift(moved);
                    gasRenderThumbs();
                }
            }
        });
    }

    if (elDrop) {
        elDrop.addEventListener('click', function (e) {
            if (e.target.closest('.gas-add-img-thumb-rm')) return;
            if (e.target.closest('.gas-add-img-thumb')) return;
            if (elFile) elFile.click();
        });
    }
    if (elBrowse) elBrowse.addEventListener('click', function () { if (elFile) elFile.click(); });
    if (elCamBtn) elCamBtn.addEventListener('click', function () { if (elCam) elCam.click(); });
    if (elFile) {
        elFile.addEventListener('change', function () {
            if (this.files) gasAddPendingFiles(this.files);
            this.value = '';
        });
    }
    if (elCam) {
        elCam.addEventListener('change', function () {
            if (this.files) gasAddPendingFiles(this.files);
            this.value = '';
        });
    }

    if (elSave) {
        elSave.addEventListener('click', function () {
            if (state.pending.length === 0) {
                gasUpdateTableRowThumb(state.barcode, state.existing[0] ? state.existing[0].url : null);
                hideModal();
                return;
            }
            var fd = new FormData();
            fd.append('item_id', '0');
            fd.append('barcode_no', state.barcode);
            for (var i = 0; i < state.pending.length; i++) {
                fd.append('images[]', state.pending[i]);
            }
            var bc = state.barcode;
            elSave.disabled = true;
            fetch(cfg.uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (text) {
                    var d;
                    try {
                        d = JSON.parse(text);
                    } catch (e1) {
                        d = { status: 'error', message: (text || '').slice(0, 240) || 'Invalid response from server' };
                    }
                    elSave.disabled = false;
                    if (d.status === 'success' && d.images && d.images.length > 0) {
                        state.pending = [];
                        state.barcode = bc;
                        gasReloadList(function () {
                            gasUpdateTableRowThumb(bc, state.existing[0] ? state.existing[0].url : null);
                            hideModal();
                        });
                    } else {
                        window.alert(d.message || 'Upload failed — no image was saved.');
                    }
                })
                .catch(function () {
                    elSave.disabled = false;
                    window.alert('Upload failed (network error).');
                });
        });
    }

    if (elClose) elClose.addEventListener('click', hideModal);
    if (elCancel) elCancel.addEventListener('click', hideModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) hideModal();
    });
})();
</script>
<?php endif; ?>
</body>
</html>
