<?php
ob_start();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold-gst.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * @param mixed $v
 * @return mixed
 */
function auragold_json_sanitize($v) {
    if (is_array($v)) {
        $out = [];
        foreach ($v as $k => $x) {
            $out[$k] = auragold_json_sanitize($x);
        }
        return $out;
    }
    if (is_float($v) && (is_nan($v) || is_infinite($v))) {
        return 0.0;
    }
    return $v;
}

/**
 * @param array<string,mixed> $data
 */
function auragold_json_out(array $data) {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    global $scanned_barcode_raw, $barcode;
    if (!empty($scanned_barcode_raw) && isset($barcode) && $barcode !== '' && $scanned_barcode_raw !== $barcode) {
        $data['scanned_barcode'] = $scanned_barcode_raw;
        $data['resolved_barcode'] = $barcode;
    }
    $clean = auragold_json_sanitize($data);
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($clean, $flags);
    if ($json === false) {
        $json = json_encode([
            'success' => false,
            'message' => 'Server error: could not encode response',
        ], $flags);
    }
    if ($json === false) {
        $json = '{"success":false,"message":"Server error"}';
    }
    echo $json;
}

$barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
$barcode = preg_replace('/[\x00-\x1F\x7F]/', '', $barcode);
$scanned_barcode_raw = $barcode;

if (empty($barcode)) {
    auragold_json_out(['success' => false, 'message' => 'Barcode is required']);
    exit;
}

$barcode_esc = esc($barcode);

// Same branch resolution as gold-and-silver.php (effective login/working branch, then legacy fallbacks).
$working_branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
if ($working_branch_id <= 0 && !empty($_SESSION['working_branch_id'])) {
    $working_branch_id = (int) $_SESSION['working_branch_id'];
} elseif ($working_branch_id <= 0 && !empty($_SESSION['branch_id'])) {
    $working_branch_id = (int) $_SESSION['branch_id'];
}
if ($working_branch_id <= 0 && function_exists('getRecordMaster')) {
    $mbr_gpb = @getRecordMaster('SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id,0)=0 AND status = 1 ORDER BY id ASC LIMIT 1');
    if ($mbr_gpb && !empty($mbr_gpb['id'])) {
        $working_branch_id = (int) $mbr_gpb['id'];
    }
}
// Optional branch_id from client: when session has a fixed branch, auragold_resolve_branch_id_for_session ignores forged values.
if (isset($_GET['branch_id'])) {
    $gpb_branch_param = (int) $_GET['branch_id'];
    if ($gpb_branch_param > 0) {
        $working_branch_id = function_exists('auragold_resolve_branch_id_for_session')
            ? auragold_resolve_branch_id_for_session($gpb_branch_param)
            : $gpb_branch_param;
    }
}

$metal_id_param = isset($_GET['metal_id']) ? (int) $_GET['metal_id'] : 0;

require_once __DIR__ . '/../includes/auragold_barcode_prefix_settings.php';
require_once __DIR__ . '/../includes/auragold_barcode_scan_map.php';
$resolved_barcode = $barcode;
if (!empty($conn) && $conn instanceof mysqli) {
    if (preg_match('/^\d{1,4}$/', $barcode) && function_exists('auragold_resolve_four_digit_scan_to_full_barcode')) {
        $padded = str_pad(preg_replace('/\D/', '', $barcode), 4, '0', STR_PAD_LEFT);
        $fromScan = auragold_resolve_four_digit_scan_to_full_barcode($conn, $padded, (int) $working_branch_id, $metal_id_param);
        if ($fromScan !== null && $fromScan !== '') {
            $barcode = $fromScan;
            $barcode_esc = esc($barcode);
            $resolved_barcode = $fromScan;
        }
    }
    if ($barcode === $scanned_barcode_raw && function_exists('auragold_resolve_scanned_barcode')) {
        $resolved_barcode = auragold_resolve_scanned_barcode($conn, $barcode, [
            'branch_id' => (int) $working_branch_id,
            'metal_id' => $metal_id_param,
        ]);
        if ($resolved_barcode !== '' && $resolved_barcode !== $barcode) {
            $barcode = $resolved_barcode;
            $barcode_esc = esc($barcode);
        }
    }
}

/** Purchase / inward screens can resolve master barcodes before local stock exists; sale & stock-out flows omit this. */
$allow_non_stock_barcode = !empty($_GET['allow_non_stock_barcode']) && (int) $_GET['allow_non_stock_barcode'] === 1;

// Sale / POS: do not allow barcode that is in transit or already stock-transferred.
if (!$allow_non_stock_barcode && $barcode !== '') {
    require_once __DIR__ . '/../includes/auragold_stock_transfer_save_helpers.php';
    if (function_exists('auragold_sale_barcode_stock_transfer_block_message') && !empty($conn) && $conn instanceof mysqli) {
        $xferBlock = auragold_sale_barcode_stock_transfer_block_message($conn, $barcode, (int) $working_branch_id);
        if ($xferBlock !== null && $xferBlock !== '') {
            auragold_json_out(['success' => false, 'message' => $xferBlock]);
            exit;
        }
    }
}

/**
 * Same rules as gold-and-silver.php gas_tbl_stock_branch_predicate: main-branch stock may be stored as NULL/0 branch_id.
 *
 * @param mysqli|null $conn
 */
function auragold_gpb_tbl_stock_branch_sql_fragment($conn, int $branch_id): string {
    static $has_branch = null;
    if ($has_branch === null && $conn) {
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'branch_id'");
        $has_branch = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }
    }
    if (!$has_branch || $branch_id <= 0) {
        return '';
    }
    $bid = (int) $branch_id;
    $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
    if ($main > 0 && $bid === $main) {
        return ' AND (s.branch_id = ' . $bid . ' OR s.branch_id IS NULL OR s.branch_id = 0)';
    }

    return ' AND COALESCE(s.branch_id, 0) = ' . $bid;
}

function auragold_gpb_in_stock_sql_fragment(bool $enforce): string {
    if (!$enforce) {
        return '';
    }

    // Never treat stock-transfer / sale outward rows as sellable on-hand.
    return " AND (IFNULL(s.current_qty,0) > 0 OR IFNULL(s.current_weight,0) > 0)
             AND (s.stock_type IS NULL OR s.stock_type NOT IN ('outward')) ";
}

/**
 * Align PC branch filter with tbl_stock / gold-and-silver main-branch NULL semantics.
 *
 * @param mysqli|null $conn
 */
function auragold_gpb_tbl_pc_branch_sql_fragment($conn, int $branch_id): string {
    static $has_pc_branch = null;
    if ($has_pc_branch === null && $conn) {
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'branch_id'");
        $has_pc_branch = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }
    }
    if (!$has_pc_branch || $branch_id <= 0) {
        return '';
    }
    $bid = (int) $branch_id;
    $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
    if ($main > 0 && $bid === $main) {
        return ' AND (pc.branch_id = ' . $bid . ' OR pc.branch_id IS NULL OR pc.branch_id = 0)';
    }

    return ' AND COALESCE(pc.branch_id, 0) = ' . $bid;
}

/**
 * Resolve branch for tbl_product_tax scope (same as get-product-details.php).
 */
function auragold_gpb_resolve_tax_branch_id(): int {
    global $working_branch_id;
    if (!empty($working_branch_id) && (int) $working_branch_id > 0) {
        return (int) $working_branch_id;
    }
    if (!empty($_SESSION['working_branch_id'])) {
        return (int) $_SESSION['working_branch_id'];
    }
    if (!empty($_SESSION['branch_id'])) {
        return (int) $_SESSION['branch_id'];
    }
    return 0;
}

/**
 * Attach GST fields (same as ajax/get-product-details.php) for sale-invoice Tax % / Tax columns.
 *
 * @param array<string,mixed> $product
 * @return array<string,mixed>
 */
function auragold_gpb_attach_gst_fields_to_product($conn, array $product, int $product_id, ?int $branch_id = null): array {
    if ($product_id <= 0) {
        return $product;
    }
    $tax_scope_id = ($branch_id !== null && $branch_id > 0) ? $branch_id : auragold_gpb_resolve_tax_branch_id();
    if ($tax_scope_id <= 0 && !empty($conn) && function_exists('getRecordMaster')) {
        $mbr_tax = @getRecordMaster('SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id,0)=0 AND status = 1 ORDER BY id ASC LIMIT 1');
        if ($mbr_tax && !empty($mbr_tax['id'])) {
            $tax_scope_id = (int) $mbr_tax['id'];
        }
    }

    $vat_value = null;
    try {
        $vat_scope = function_exists('auragold_tbl_product_tax_branch_scope_sql')
            ? auragold_tbl_product_tax_branch_scope_sql($conn, $product_id, $tax_scope_id)
            : '';
        $vat_tax = getRecord("
            SELECT pt.tax_value FROM tbl_product_tax pt
            WHERE pt.product_id = $product_id AND pt.tax_type = 'VAT' AND (pt.status = 1 OR pt.status IS NULL)
            $vat_scope
            ORDER BY pt.id DESC LIMIT 1
        ");
        $vat_value = $vat_tax && isset($vat_tax['tax_value']) ? (float) $vat_tax['tax_value'] : null;
    } catch (Throwable $e) {
        $vat_value = null;
    }

    $product_tax_rows = [];
    try {
        $pt_scope = function_exists('auragold_tbl_product_tax_branch_scope_sql')
            ? auragold_tbl_product_tax_branch_scope_sql($conn, $product_id, $tax_scope_id)
            : '';
        $product_tax_rows = getList("
            SELECT pt.tax_type, pt.tax_value
            FROM tbl_product_tax pt
            WHERE pt.product_id = $product_id AND (pt.status = 1 OR pt.status IS NULL)
            $pt_scope
        ");
        if (!is_array($product_tax_rows)) {
            $product_tax_rows = [];
        }
    } catch (Throwable $e) {
        $product_tax_rows = [];
    }

    $gst_local_percent = 0.0;
    $gst_interstate_percent = 0.0;
    $gst_tax_breakdown = ['local_state' => [], 'out_of_state' => []];

    try {
        if (function_exists('auragold_product_gst_tax_breakdown')) {
            $gst_tax_breakdown = auragold_product_gst_tax_breakdown($conn, $product_id, $tax_scope_id);
        }
        if (function_exists('auragold_product_gst_percent_by_supply_scope')) {
            $scopes = auragold_product_gst_percent_by_supply_scope($conn, $product_id, $tax_scope_id);
            $gst_local_percent = (float) ($scopes['local'] ?? 0);
            $gst_interstate_percent = (float) ($scopes['interstate'] ?? 0);
        }
    } catch (Throwable $e) {
        $gst_local_percent = 0.0;
        $gst_interstate_percent = 0.0;
        $gst_tax_breakdown = ['local_state' => [], 'out_of_state' => []];
    }

    if (
        ($gst_local_percent < 0.00001 && $gst_interstate_percent < 0.00001)
        && empty($gst_tax_breakdown['local_state'])
        && empty($gst_tax_breakdown['out_of_state'])
        && is_array($product_tax_rows)
    ) {
        try {
            foreach ($product_tax_rows as $t) {
                $taxTypeRaw = trim((string) ($t['tax_type'] ?? ''));
                if (strtoupper($taxTypeRaw) === 'VAT') {
                    continue;
                }
                $percent = (float) ($t['tax_value'] ?? 0);
                $scope = '';
                $tn = function_exists('auragold_normalize_state_label') ? auragold_normalize_state_label($taxTypeRaw) : strtolower($taxTypeRaw);
                if ($tn === 'igst') {
                    $scope = 'out_of_state';
                } elseif ($tn === 'cgst' || $tn === 'sgst') {
                    $scope = 'local_state';
                }
                if ($scope !== 'local_state' && $scope !== 'out_of_state') {
                    continue;
                }
                $lineItem = [
                    'name' => $taxTypeRaw,
                    'default_value' => $percent,
                    'gst_supply_scope' => $scope,
                ];
                if ($scope === 'local_state') {
                    $gst_local_percent += $percent;
                    $gst_tax_breakdown['local_state'][] = $lineItem;
                } else {
                    $gst_interstate_percent += $percent;
                    $gst_tax_breakdown['out_of_state'][] = $lineItem;
                }
            }
        } catch (Throwable $e2) {
            // keep zeros
        }
    }

    $taxes_for_api = [];
    try {
        foreach ($gst_tax_breakdown['local_state'] ?? [] as $x) {
            $taxes_for_api[] = [
                'tax_type' => (string) ($x['name'] ?? ''),
                'tax_value' => (float) ($x['default_value'] ?? 0),
                'gst_supply_scope' => 'local_state',
            ];
        }
        foreach ($gst_tax_breakdown['out_of_state'] ?? [] as $x) {
            $taxes_for_api[] = [
                'tax_type' => (string) ($x['name'] ?? ''),
                'tax_value' => (float) ($x['default_value'] ?? 0),
                'gst_supply_scope' => 'out_of_state',
            ];
        }
    } catch (Throwable $e) {
        $taxes_for_api = [];
    }

    if (count($taxes_for_api) === 0 && is_array($product_tax_rows)) {
        foreach ($product_tax_rows as $t) {
            $taxTypeRaw = trim((string) ($t['tax_type'] ?? ''));
            if ($taxTypeRaw === '' || strtoupper($taxTypeRaw) === 'VAT') {
                continue;
            }
            $taxes_for_api[] = [
                'tax_type' => $taxTypeRaw,
                'tax_value' => (float) ($t['tax_value'] ?? 0),
            ];
        }
    }

    $gst_invoice_slab_percent = ($gst_local_percent > 0.0 || $gst_interstate_percent > 0.0)
        ? max($gst_local_percent, $gst_interstate_percent)
        : 0.0;

    $total_tax_percent = null;
    if ($vat_value !== null) {
        $gst_has_any = ($gst_local_percent > 0.00001 || $gst_interstate_percent > 0.00001);
        $bd_ls = isset($gst_tax_breakdown['local_state']) && is_array($gst_tax_breakdown['local_state']) ? count($gst_tax_breakdown['local_state']) : 0;
        $bd_os = isset($gst_tax_breakdown['out_of_state']) && is_array($gst_tax_breakdown['out_of_state']) ? count($gst_tax_breakdown['out_of_state']) : 0;
        if (!$gst_has_any && $bd_ls === 0 && $bd_os === 0) {
            $total_tax_percent = $vat_value;
        }
    }

    $product['vat_value'] = $vat_value;
    $product['total_tax_percent'] = $total_tax_percent;
    $product['gst_local_percent'] = $gst_local_percent;
    $product['gst_interstate_percent'] = $gst_interstate_percent;
    $product['gst_invoice_slab_percent'] = $gst_invoice_slab_percent > 0 ? $gst_invoice_slab_percent : null;
    $product['gst_tax_breakdown'] = $gst_tax_breakdown;
    $product['taxes'] = $taxes_for_api;

    return $product;
}

/**
 * After stock journal merge/outward flows, line tbl_stock rows may be zeroed while balance lives on a pooled outward row
 * (reference_barcodes lists physical tags). Treat barcode as available when that pool still has qty/weight.
 *
 * @param mysqli|null $conn
 */
function auragold_gpb_barcode_in_pooled_outward($conn, string $barcode_esc, int $branch_id): bool {
    static $has_ref = null;
    if ($has_ref === null && $conn) {
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_barcodes'");
        $has_ref = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }
    }
    if (!$has_ref || $branch_id <= 0 || $barcode_esc === '') {
        return false;
    }
    $bid = (int) $branch_id;
    $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
    $br_pred = ($main > 0 && $bid === $main)
        ? "(branch_id = $bid OR branch_id IS NULL OR branch_id = 0)"
        : "COALESCE(branch_id, 0) = $bid";
    $r = getRecord("
        SELECT id
        FROM tbl_stock
        WHERE status = 1
        AND stock_type = 'outward'
        AND $br_pred
        AND reference_barcodes IS NOT NULL
        AND TRIM(reference_barcodes) != ''
        AND FIND_IN_SET(
            '$barcode_esc',
            REPLACE(REPLACE(REPLACE(TRIM(reference_barcodes), ' ', ''), CHAR(9), ''), CHAR(13), '')
        ) > 0
        AND (IFNULL(current_qty, 0) > 0 OR IFNULL(current_weight, 0) > 0)
        ORDER BY id DESC
        LIMIT 1
    ");
    return $r && !empty($r['id']);
}

/**
 * gold-and-silver.php lists stock using net balance per barcode+branch (sums inward rows minus outward).
 * get-product-by-barcode used to require one tbl_stock row with current_qty/current_weight &gt; 0, which misses
 * cases where net is positive but line rows are zeroed. Align sale scan with the same net formula + pick_id.
 *
 * @param mysqli|null $conn
 * @return array<string,mixed>|null Same shape as the primary tbl_stock SELECT in this file
 */
function auragold_gpb_stock_row_from_net_balance($conn, string $barcode_esc, int $branch_id): ?array {
    if (!$conn || $barcode_esc === '' || $branch_id <= 0) {
        return null;
    }
    require_once dirname(__DIR__) . '/includes/gold_silver_stock_list_fetch.php';
    $gpb_bsql = auragold_gpb_tbl_stock_branch_sql_fragment($conn, $branch_id);
    $gas_stk_in_types_sql = "'opening','purchase','stock_journal','balance','sale_return'";
    $in_qty = gas_stock_inward_qty_expr('s');
    $in_wt = gas_stock_inward_wt_expr('s');
    $agg = getRecord("
        SELECT
            (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN $in_qty ELSE 0 END)
             - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)) AS bal_qty,
            (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN $in_wt ELSE 0 END)
             - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)) AS bal_wt,
            COALESCE(
                MAX(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) AND ($in_wt) > 0.00001 THEN s.id END),
                MAX(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN s.id END),
                MAX(s.id)
            ) AS pick_id
        FROM tbl_stock s
        WHERE s.status = 1
        AND s.barcode = '$barcode_esc'
        $gpb_bsql
        GROUP BY s.barcode, s.branch_id
        HAVING (bal_qty > 0.00001 OR bal_wt > 0.00001)
        LIMIT 1
    ");
    if (!$agg || empty($agg['pick_id'])) {
        return null;
    }
    $pick_id = (int) $agg['pick_id'];
    if ($pick_id <= 0) {
        return null;
    }
    $stock_check = getRecord("
        SELECT s.id, s.product_id, s.product_characteristic_id, s.barcode, s.metal_id,
               s.current_qty, s.current_weight, s.final_weight, s.rate, s.value,
               s.opening_purity, s.opening_weight, s.opening_qty
        FROM tbl_stock s
        WHERE s.id = $pick_id AND s.status = 1
        LIMIT 1
    ");
    if (!$stock_check) {
        return null;
    }
    $stock_check['current_qty'] = isset($agg['bal_qty']) ? (float) $agg['bal_qty'] : (float) ($stock_check['current_qty'] ?? 0);
    $stock_check['current_weight'] = isset($agg['bal_wt']) ? (float) $agg['bal_wt'] : (float) ($stock_check['current_weight'] ?? 0);

    return $stock_check;
}

/**
 * Stock-journal merge sets current_* to 0 on line barcodes while opening_* keeps the tag weight.
 *
 * @param array<string,mixed> $stock_row
 * @return array<string,mixed>
 */
function auragold_gpb_enrich_zeroed_stock_row(array $stock_row, string $barcode_esc): array {
    $cq = (float) ($stock_row['current_qty'] ?? 0);
    $cw = (float) ($stock_row['current_weight'] ?? 0);
    $oq = (float) ($stock_row['opening_qty'] ?? 0);
    $ow = (float) ($stock_row['opening_weight'] ?? 0);
    if ($cq <= 0 && $oq > 0) {
        $stock_row['current_qty'] = $oq;
    }
    if ($cw <= 0 && $ow > 0) {
        $stock_row['current_weight'] = $ow;
    }
    if ($cq <= 0 || $cw <= 0) {
        $sj = getRecord("
            SELECT quantity, gross_weight, net_weight
            FROM tbl_stock_journal
            WHERE barcode = '$barcode_esc' AND status = 'active'
            ORDER BY id DESC
            LIMIT 1
        ");
        if ($sj) {
            if ($cq <= 0 && isset($sj['quantity']) && (float) $sj['quantity'] > 0) {
                $stock_row['current_qty'] = (float) $sj['quantity'];
            }
            $sj_wt = 0.0;
            if (isset($sj['gross_weight']) && (float) $sj['gross_weight'] > 0) {
                $sj_wt = (float) $sj['gross_weight'];
            } elseif (isset($sj['net_weight']) && (float) $sj['net_weight'] > 0) {
                $sj_wt = (float) $sj['net_weight'];
            }
            if ($cw <= 0 && $sj_wt > 0) {
                $stock_row['current_weight'] = $sj_wt;
            }
        }
    }

    return $stock_row;
}

/**
 * Line barcode cleared after stock-journal merge but still listed on a pooled outward row.
 *
 * @param mysqli|null $conn
 * @return array<string,mixed>|null
 */
function auragold_gpb_stock_row_from_pooled_reference($conn, string $barcode_esc, int $branch_id): ?array {
    if (!$conn || $barcode_esc === '' || $branch_id <= 0) {
        return null;
    }
    if (!auragold_gpb_barcode_in_pooled_outward($conn, $barcode_esc, $branch_id)) {
        return null;
    }
    $gpb_bsql = auragold_gpb_tbl_stock_branch_sql_fragment($conn, $branch_id);
    $stock_row = getRecord("
        SELECT s.id, s.product_id, s.product_characteristic_id, s.barcode, s.metal_id,
               s.current_qty, s.current_weight, s.final_weight, s.rate, s.value,
               s.opening_purity, s.opening_weight, s.opening_qty
        FROM tbl_stock s
        WHERE s.barcode = '$barcode_esc' AND s.status = 1
        $gpb_bsql
        ORDER BY s.id DESC
        LIMIT 1
    ");
    if (!$stock_row) {
        return null;
    }

    return auragold_gpb_enrich_zeroed_stock_row($stock_row, $barcode_esc);
}

/**
 * Browser URL for a stored image_path (same rules as ajax/gas-list-stock-journal-images.php).
 */
function auragold_gpb_public_url_from_journal_image_path(string $path): string {
    global $SiteUrl;
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $base = isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') : '';
    $appRoot = '';
    if ($base === '') {
        $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if ($sn !== '' && $sn !== '/') {
            $dir = rtrim(dirname($sn), '/');
            if (preg_match('#^(.*)/admin(?:/|$)#u', $dir . '/', $m)) {
                $appRoot = rtrim($m[1], '/') ?: '';
            }
        }
    }
    $rel = ltrim($path, '/');
    if ($rel === '') {
        return '';
    }
    $under = auragold_uploads_public_rel($rel);
    if (strpos($path, '/') === 0) {
        if (preg_match('#^/uploads/#', $path)) {
            $trail = ltrim($path, '/');
            $underAbs = auragold_uploads_public_rel($trail);
            if ($base !== '') {
                return $base . '/' . $underAbs;
            }
            if ($appRoot !== '') {
                return rtrim($appRoot, '/') . '/' . $underAbs;
            }
            return '/' . $underAbs;
        }
        return $path;
    }
    if ($base !== '') {
        return $base . '/' . $under;
    }
    if ($appRoot !== '' && $appRoot !== '/' && $appRoot !== '.') {
        return rtrim($appRoot, '/') . '/' . $under;
    }
    return '/' . $under;
}

/**
 * Public URLs for tbl_stock_journal_images rows (paths stored as uploads/stock_journal/...).
 *
 * @param mysqli|null $conn
 * @return list<string>
 */
function auragold_gpb_journal_image_urls_for_barcode($conn, string $barcode_esc): array {
    if (!$conn || $barcode_esc === '') {
        return [];
    }
    static $has_table = null;
    if ($has_table === null) {
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal_images'");
        $has_table = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }
    }
    if (!$has_table) {
        return [];
    }
    $rs = @mysqli_query(
        $conn,
        "SELECT image_path FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$barcode_esc') ORDER BY id ASC"
    );
    if (!$rs) {
        return [];
    }
    $urls = [];
    while ($row = mysqli_fetch_assoc($rs)) {
        $u = auragold_gpb_public_url_from_journal_image_path((string) ($row['image_path'] ?? ''));
        if ($u !== '') {
            $urls[] = $u;
        }
    }
    mysqli_free_result($rs);
    return $urls;
}

/**
 * Same as barcode lookup but keyed by tbl_stock_journal.id (uploads often store item_id; barcode_no can be blank/mismatched).
 *
 * @param mysqli|null $conn
 * @return list<string>
 */
function auragold_gpb_journal_image_urls_for_item_id($conn, int $item_id): array {
    if (!$conn || $item_id <= 0) {
        return [];
    }
    static $has_table = null;
    if ($has_table === null) {
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal_images'");
        $has_table = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }
    }
    if (!$has_table) {
        return [];
    }
    $jid = (int) $item_id;
    $rs = @mysqli_query(
        $conn,
        "SELECT image_path FROM tbl_stock_journal_images WHERE item_id = $jid ORDER BY id ASC"
    );
    if (!$rs) {
        return [];
    }
    $urls = [];
    while ($row = mysqli_fetch_assoc($rs)) {
        $u = auragold_gpb_public_url_from_journal_image_path((string) ($row['image_path'] ?? ''));
        if ($u !== '') {
            $urls[] = $u;
        }
    }
    mysqli_free_result($rs);
    return $urls;
}

/**
 * @param mysqli|null $conn
 * @param array<string,mixed> $product
 * @return array<string,mixed>
 */
function auragold_gpb_attach_journal_images_to_product($conn, array $product, string $barcode_esc, ?int $journal_item_id = null): array {
    $urls = auragold_gpb_journal_image_urls_for_barcode($conn, $barcode_esc);
    if ($urls === []) {
        $alt = mysqli_real_escape_string($conn, trim((string) ($product['barcode'] ?? '')));
        if ($alt !== '' && $alt !== $barcode_esc) {
            $urls = auragold_gpb_journal_image_urls_for_barcode($conn, $alt);
        }
    }
    if ($urls === [] && $journal_item_id !== null && $journal_item_id > 0) {
        $urls = auragold_gpb_journal_image_urls_for_item_id($conn, $journal_item_id);
    }
    $product['journal_image_urls'] = $urls;
    $product['journal_image_primary'] = $urls[0] ?? '';
    return $product;
}

/**
 * Latest active tbl_stock_journal row for this barcode: attach full row as stock_journal
 * and merge common scalars so sale-invoice row fields (rate, purity, weights, etc.) populate.
 */
function attach_latest_stock_journal_to_product(array $product, $barcode_esc, $conn = null) {
    $sj = getRecord("SELECT * FROM tbl_stock_journal WHERE barcode = '$barcode_esc' AND status = 'active' ORDER BY id DESC LIMIT 1");
    if (!$sj || !is_array($sj)) {
        if ($conn instanceof mysqli) {
            $product = auragold_gpb_attach_journal_images_to_product($conn, $product, $barcode_esc, null);
        }
        return $product;
    }
    $product['stock_journal'] = $sj;
    // Do not override metal_id from stock journal — journal links can point at wrong metal;
    // tbl_stock.metal_id + product characteristics define the sale modal tab (e.g. Diamond & Stones).
    $scalarKeys = [
        'rate', 'gross_weight', 'less_weight', 'net_weight', 'final_weight', 'quantity', 'karat',
        'pkt_wt', 'pkt_less_wt', 'purity', 'purity_weight', 'pure_weight', 'metal_value', 'metal_cost',
        'amount', 'making_rate', 'making_amount', 'making_type', 'making_cost',
        'wastage_per', 'wastage_wt', 'alloy_wt',
        'gold_loss_1', 'gold_loss_2', 'setting_charge',
        'stone_weight', 'stone_rate', 'stone_amount', 'stone_cost',
        'diamond_amount', 'requested_purity', 'requested',
        'discount', 'discount_per', 'discount_amount',
        'purchase_amount', 'sale_percent', 'sale_amount',
        'tax_amount', 'net_amount', 'net_amt_with_tax',
        'hallmark_amount', 'hallmark_rate', 'reverse',
    ];
    foreach ($scalarKeys as $k) {
        if (!array_key_exists($k, $sj)) {
            continue;
        }
        $v = $sj[$k];
        if ($v === null) {
            continue;
        }
        if (is_string($v) && trim($v) === '') {
            continue;
        }
        $product[$k] = $v;
    }
    if (array_key_exists('purity', $sj) && $sj['purity'] !== null && $sj['purity'] !== '' && !(is_string($sj['purity']) && trim($sj['purity']) === '')) {
        $product['opening_purity'] = $sj['purity'];
        $product['purity'] = $sj['purity'];
    }
    // Do not map stock journal quantity → metal_qty: journal qty is often total line / inventory, not "Metal Qty" (pieces) in the product modal.
    if (isset($sj['gross_weight']) && $sj['gross_weight'] !== '' && $sj['gross_weight'] !== null) {
        $gw = (float)$sj['gross_weight'];
        if ($gw > 0) {
            $product['metal_weight'] = $gw;
        }
    }
    // Sale invoice "Metal Rate" column uses metal_rate in calcs; mirror journal rate when present
    if (array_key_exists('metal_rate', $sj) && $sj['metal_rate'] !== null && $sj['metal_rate'] !== '' && !(is_string($sj['metal_rate']) && trim($sj['metal_rate']) === '')) {
        $product['metal_rate'] = $sj['metal_rate'];
    } elseif (isset($product['rate']) && $product['rate'] !== null && $product['rate'] !== '' && !(is_string($product['rate']) && trim($product['rate']) === '')) {
        $product['metal_rate'] = $product['rate'];
    }
    if ($conn instanceof mysqli) {
        $jid = !empty($sj['id']) ? (int) $sj['id'] : 0;
        $product = auragold_gpb_attach_journal_images_to_product($conn, $product, $barcode_esc, $jid > 0 ? $jid : null);
    }
    return $product;
}

require_once __DIR__ . '/../includes/diamond_barcode_cursor.php';

/**
 * Build sale-invoice product payload from a tbl_stock row + product join (shared by single scan + diamond cursor).
 *
 * @param array<string,mixed> $stock_check
 * @return array<string,mixed>|null
 */
function auragold_assemble_product_from_stock_check(array $stock_check, string $barcode, string $barcode_esc): ?array {
    $pid = (int) $stock_check['product_id'];
    $pcid = isset($stock_check['product_characteristic_id']) && $stock_check['product_characteristic_id'] !== '' && $stock_check['product_characteristic_id'] !== null
        ? (int) $stock_check['product_characteristic_id'] : null;
    $char_join = $pcid ? "LEFT JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.id = $pcid" : "LEFT JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.status = 1";
    $stock_product = getRecord("
        SELECT p.id as product_id, p.name as product_name, p.article, p.alternate_name,
               pc.id as characteristic_id, pc.barcode as pc_barcode, pc.opening_weight as pc_opening_weight,
               pc.opening_purity as pc_opening_purity, pc.opening_qty as pc_opening_qty,
               pc.final_weight as pc_final_weight, pc.rate as pc_rate, pc.value as pc_value,
               pc.hsn, pc.sku_code, pc.making_on, pc.diamond_category, pc.carat, pc.discount,
               m.display_name as metal_name, m.id as metal_id
        FROM tbl_products p
        $char_join
        LEFT JOIN tbl_metal m ON (pc.metal_id = m.id)
        WHERE p.id = $pid AND p.status = 1
        ORDER BY pc.id DESC
        LIMIT 1
    ");
    if (!$stock_product) {
        return null;
    }
    $product_id = (int) $stock_product['product_id'];

    $st_current_qty = isset($stock_check['current_qty']) ? (float) $stock_check['current_qty'] : 0;
    $st_current_weight = isset($stock_check['current_weight']) ? (float) $stock_check['current_weight'] : 0;
    $st_opening_qty = isset($stock_check['opening_qty']) ? (float) $stock_check['opening_qty'] : (isset($stock_product['pc_opening_qty']) ? (float) $stock_product['pc_opening_qty'] : 0);
    $st_opening_weight = isset($stock_check['opening_weight']) ? (float) $stock_check['opening_weight'] : (isset($stock_product['pc_opening_weight']) ? (float) $stock_product['pc_opening_weight'] : 0);
    // Metal Qty in modal = piece count (default 1). Do not use pc.opening_qty — it often matches gross/inventory (e.g. 10) and is not "pieces".
    $metal_qty = 1;
    $metal_weight = $st_current_weight > 0 ? $st_current_weight : $st_opening_weight;
    $metal_qty = (float) $metal_qty;
    $metal_weight = (float) $metal_weight;

    $product = [
        'id' => $stock_product['product_id'],
        'name' => $stock_product['product_name'],
        'article' => $stock_product['article'],
        'alternate_name' => $stock_product['alternate_name'],
        'characteristic_id' => $stock_product['characteristic_id'] ?: $pcid,
        'barcode' => $barcode,
        'metal_name' => $stock_product['metal_name'],
        'metal_id' => $stock_product['metal_id'],
        'opening_weight' => $stock_check['opening_weight'] ?? $stock_product['pc_opening_weight'],
        'opening_purity' => $stock_check['opening_purity'] ?? $stock_product['pc_opening_purity'],
        'opening_qty' => $stock_check['opening_qty'] ?? $stock_product['pc_opening_qty'],
        'final_weight' => $stock_check['final_weight'] ?? $stock_product['pc_final_weight'],
        'rate' => $stock_check['rate'] ?? $stock_product['pc_rate'],
        'value' => $stock_check['value'] ?? $stock_product['pc_value'],
        'purity' => $stock_check['opening_purity'] ?? $stock_product['pc_opening_purity'],
        'metal_qty' => $metal_qty,
        'metal_weight' => $metal_weight,
        'hsn' => $stock_product['hsn'],
        'sku_code' => $stock_product['sku_code'],
        'making_on' => $stock_product['making_on'],
        'diamond_category' => $stock_product['diamond_category'],
        'carat' => $stock_product['carat'],
        'discount' => $stock_product['discount'],
    ];
    global $conn, $working_branch_id;
    $product = auragold_gpb_attach_gst_fields_to_product($conn, $product, $product_id, $working_branch_id > 0 ? $working_branch_id : null);
    if (!empty($stock_check['metal_id'])) {
        $smid = (int) $stock_check['metal_id'];
        if ($smid > 0) {
            $mrow = getRecord("SELECT id, display_name FROM tbl_metal WHERE id = $smid AND status = 1 LIMIT 1");
            if ($mrow) {
                $product['metal_id'] = $smid;
                $product['metal_name'] = $mrow['display_name'];
            }
        }
    }
    $product = attach_latest_stock_journal_to_product($product, $barcode_esc, $conn);

    return $product;
}

/**
 * Merged diamond barcode: cursor over purchase lines → one product per unsold line.
 *
 * @param int|null $branch_id Login branch — tbl_stock rows must match (sub-branch barcodes ignored at main branch).
 * @return list<array<string,mixed>>|null null = fewer than 2 purchase diamond lines (use legacy path)
 */
function auragold_try_diamond_merged_products_from_purchase_cursor(string $barcode, string $barcode_esc, ?int $branch_id = null, bool $allow_non_stock_barcode = false): ?array {
    global $conn;
    $lines = auragold_composite_purchase_lines_for_barcode($barcode_esc);
    if (count($lines) < 2) {
        return null;
    }

    $tag_has_stock = false;
    if ($conn instanceof mysqli && $branch_id !== null && (int) $branch_id > 0) {
        $tag_row = auragold_gpb_stock_row_from_net_balance($conn, $barcode_esc, (int) $branch_id);
        if ($tag_row) {
            $tq = (float) ($tag_row['current_qty'] ?? 0);
            $tw = (float) ($tag_row['current_weight'] ?? 0);
            $tag_has_stock = ($tq > 0 || $tw > 0);
        }
    }
    if (!$tag_has_stock && $conn instanceof mysqli) {
        $bsql = auragold_gpb_tbl_stock_branch_sql_fragment($conn, (int) ($branch_id ?? 0));
        $any = getRecord("
            SELECT s.id FROM tbl_stock s
            WHERE TRIM(s.barcode) = TRIM('$barcode_esc') AND s.status = 1
            $bsql
            AND (IFNULL(s.current_qty, 0) > 0 OR IFNULL(s.current_weight, 0) > 0)
            LIMIT 1
        ");
        $tag_has_stock = (bool) $any;
    }

    $products = [];
    foreach ($lines as $line) {
        $pid = (int) ($line['product_id'] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $line_bc = isset($line['barcode_trim']) ? trim((string) $line['barcode_trim']) : '';
        if ($line_bc === '') {
            $line_bc = $barcode;
        }
        $line_bc_esc = esc($line_bc);
        $pcid = isset($line['product_characteristic_id']) && $line['product_characteristic_id'] !== '' && $line['product_characteristic_id'] !== null
            ? (int) $line['product_characteristic_id'] : null;
        if (auragold_sale_invoice_line_exists_for_barcode_product($line_bc_esc, $pid, $pcid)) {
            continue;
        }
        $purchase_item_id = (int) ($line['purchase_item_id'] ?? 0);
        $stock_check = ($purchase_item_id > 0)
            ? auragold_stock_row_for_purchase_invoice_item($line_bc_esc, $purchase_item_id, $branch_id)
            : auragold_stock_row_for_purchase_line($line_bc_esc, $pid, $pcid, $branch_id);
        if (!$stock_check && $purchase_item_id > 0 && ($allow_non_stock_barcode || $tag_has_stock)) {
            $stock_check = auragold_synthetic_stock_from_purchase_invoice_item($purchase_item_id, $line_bc_esc);
        }
        if (!$stock_check) {
            continue;
        }
        if (!$allow_non_stock_barcode && !$tag_has_stock) {
            $cq = (float) ($stock_check['current_qty'] ?? 0);
            $cw = (float) ($stock_check['current_weight'] ?? 0);
            if ($cq <= 0 && $cw <= 0) {
                continue;
            }
        }
        $p = auragold_assemble_product_from_stock_check($stock_check, $line_bc, $line_bc_esc);
        if ($p) {
            if ($purchase_item_id > 0) {
                $p['purchase_invoice_item_id'] = $purchase_item_id;
                $pii_row = @getRecord('
                    SELECT diamond_category, product_name, carat, net_amount, net_amt_with_tax, gross_weight,
                           less_weight, net_weight, final_weight, stone_weight, stone_amount, diamond_amount,
                           tax_amount, calculation_type, metal_rate, rate, purchase_amount, making_amount, purity
                    FROM tbl_purchase_invoice_items WHERE id = ' . $purchase_item_id . ' AND status = 1 LIMIT 1
                ');
                if ($pii_row && !empty($pii_row['diamond_category']) && trim((string) $pii_row['diamond_category']) !== '') {
                    $p['diamond_category'] = trim((string) $pii_row['diamond_category']);
                }
                if (!empty($pii_row['product_name']) && trim((string) $pii_row['product_name']) !== '') {
                    $p['name'] = trim((string) $pii_row['product_name']);
                    $p['product_name'] = $p['name'];
                }
                if ($pii_row && is_array($pii_row)) {
                    if (!isset($p['stock_journal']) || !is_array($p['stock_journal'])) {
                        $p['stock_journal'] = [];
                    }
                    foreach ([
                        'net_amount', 'net_amt_with_tax', 'gross_weight', 'less_weight', 'net_weight', 'final_weight',
                        'stone_weight', 'stone_amount', 'diamond_amount', 'tax_amount', 'purchase_amount',
                        'making_amount', 'purity', 'carat', 'rate',
                    ] as $jk) {
                        if (array_key_exists($jk, $pii_row) && $pii_row[$jk] !== null && $pii_row[$jk] !== '') {
                            $p['stock_journal'][$jk] = $pii_row[$jk];
                        }
                    }
                    if (!empty($pii_row['calculation_type'])) {
                        $p['stock_journal']['calculation'] = $pii_row['calculation_type'];
                    }
                    if (isset($pii_row['metal_rate']) && $pii_row['metal_rate'] !== null && $pii_row['metal_rate'] !== '') {
                        $p['stock_journal']['metal_rate'] = $pii_row['metal_rate'];
                    }
                    if (!empty($pii_row['diamond_category']) && trim((string) $pii_row['diamond_category']) !== '') {
                        $p['stock_journal']['category'] = trim((string) $pii_row['diamond_category']);
                    }
                    if (!empty($pii_row['carat']) && trim((string) $pii_row['carat']) !== '') {
                        $p['carat'] = trim((string) $pii_row['carat']);
                    }
                }
            }
            $products[] = $p;
        }
    }

    return $products;
}

try {
    global $conn;
// 0) Merged diamond / stone: same barcode on multiple purchase lines → return all unsold (sale invoice only passes expand_invoice_siblings)
if (!empty($_GET['expand_invoice_siblings']) && (int) $_GET['expand_invoice_siblings'] === 1) {
    $gpb_branch = $working_branch_id > 0 ? $working_branch_id : null;
    $merged = auragold_try_diamond_merged_products_from_purchase_cursor($barcode, $barcode_esc, $gpb_branch, $allow_non_stock_barcode);
    if ($merged !== null) {
        if (count($merged) === 0) {
            auragold_json_out([
                'success' => false,
                'message' => 'No unsold diamond/stone lines available for this barcode',
            ]);
            exit;
        }
        // Always return `products` (even one line) so the client uses the same path and keeps purchase_invoice_item_id.
        auragold_json_out(['success' => true, 'products' => $merged, 'multi' => count($merged) > 1]);
        exit;
    }
}

// 1) First check tbl_stock for barcode (stock is source of truth for scanned barcodes) — login branch + optional positive inventory
    $gpb_bsql = auragold_gpb_tbl_stock_branch_sql_fragment($conn, $working_branch_id);
    $gpb_isql_strict = auragold_gpb_in_stock_sql_fragment(!$allow_non_stock_barcode);
$stock_check = getRecord("
    SELECT s.id, s.product_id, s.product_characteristic_id, s.barcode, s.metal_id,
           s.current_qty, s.current_weight, s.final_weight, s.rate, s.value,
           s.opening_purity, s.opening_weight, s.opening_qty
    FROM tbl_stock s
    WHERE s.barcode = '$barcode_esc' AND s.status = 1
    $gpb_bsql
    $gpb_isql_strict
    ORDER BY s.id DESC
    LIMIT 1
");

// Stock journal merge zeroes per-line tbl_stock rows; balance may remain on a pooled outward row (reference_barcodes).
if (!$stock_check && !$allow_non_stock_barcode) {
    $stock_relaxed = getRecord("
    SELECT s.id, s.product_id, s.product_characteristic_id, s.barcode, s.metal_id,
           s.current_qty, s.current_weight, s.final_weight, s.rate, s.value,
           s.opening_purity, s.opening_weight, s.opening_qty
    FROM tbl_stock s
    WHERE s.barcode = '$barcode_esc' AND s.status = 1
    $gpb_bsql
    ORDER BY s.id DESC
    LIMIT 1
    ");
    if ($stock_relaxed) {
        $r_cq = (float) ($stock_relaxed['current_qty'] ?? 0);
        $r_cw = (float) ($stock_relaxed['current_weight'] ?? 0);
        $pool_branch = $working_branch_id > 0 ? $working_branch_id : 0;
        if ($r_cq <= 0 && $r_cw <= 0 && $pool_branch > 0 && auragold_gpb_barcode_in_pooled_outward($conn, $barcode_esc, $pool_branch)) {
            $stock_check = auragold_gpb_enrich_zeroed_stock_row($stock_relaxed, $barcode_esc);
        }
    }
}

// Same net on-hand as gold-and-silver.php (aggregate inward − outward); single-row current_* can all be zero.
if (!$stock_check && !$allow_non_stock_barcode && $working_branch_id > 0) {
    $stock_check = auragold_gpb_stock_row_from_net_balance($conn, $barcode_esc, $working_branch_id);
}

// Pooled outward (reference_barcodes) when per-line rows were zeroed but tag is still in the pool.
if (!$stock_check && !$allow_non_stock_barcode && $working_branch_id > 0) {
    $stock_check = auragold_gpb_stock_row_from_pooled_reference($conn, $barcode_esc, $working_branch_id);
}

if ($stock_check) {
    $pid = (int) $stock_check['product_id'];
    $pcid = isset($stock_check['product_characteristic_id']) && $stock_check['product_characteristic_id'] !== '' && $stock_check['product_characteristic_id'] !== null
        ? (int) $stock_check['product_characteristic_id'] : null;
    if (auragold_sale_invoice_line_exists_for_barcode_product($barcode_esc, $pid, $pcid)) {
        auragold_json_out([
            'success' => false,
            'message' => 'Barcode no already sold',
        ]);
        exit;
    }
    $product = auragold_assemble_product_from_stock_check($stock_check, $barcode, $barcode_esc);
    if ($product) {
        $out = ['success' => true, 'product' => $product];
        // Sale / purchase: one scan can queue every line on the same composite purchase tag (jewellery + diamond + stone).
        if (!empty($_GET['expand_invoice_siblings']) && (int) $_GET['expand_invoice_siblings'] === 1
            && !empty($_GET['include_purchase_invoice_sibling_barcodes']) && (int) $_GET['include_purchase_invoice_sibling_barcodes'] === 1) {
            try {
                $sibling_barcodes = auragold_composite_sibling_barcodes_for_scan($barcode, $barcode_esc);
                if (empty($sibling_barcodes)) {
                    $anchor = getRecord("
                        SELECT invoice_id
                        FROM tbl_purchase_invoice_items pii
                        WHERE pii.status = 1
                        AND " . auragold_pii_sql_barcode_or_tag_match('pii', $barcode_esc) . "
                        ORDER BY pii.id DESC
                        LIMIT 1
                    ");
                    if ($anchor) {
                        $inv_id = (int) $anchor['invoice_id'];
                        if ($inv_id > 0) {
                            $cnt_row = getRecord("SELECT COUNT(*) AS c FROM tbl_purchase_invoice_items WHERE invoice_id = $inv_id AND status = 1");
                            $line_count = $cnt_row ? (int) ($cnt_row['c'] ?? 0) : 0;
                            if ($line_count > 0 && $line_count <= 40) {
                                $all_bc = getList("
                                    SELECT TRIM(pii.barcode) AS bc
                                    FROM tbl_purchase_invoice_items pii
                                    WHERE pii.invoice_id = $inv_id
                                    AND pii.status = 1
                                    AND pii.barcode IS NOT NULL AND TRIM(pii.barcode) != ''
                                    ORDER BY pii.id ASC
                                ");
                                if (is_array($all_bc)) {
                                    foreach ($all_bc as $ar) {
                                        $b = isset($ar['bc']) ? trim((string) $ar['bc']) : '';
                                        if ($b === '' || strcasecmp($b, $barcode) === 0) {
                                            continue;
                                        }
                                        $sibling_barcodes[] = $b;
                                    }
                                }
                                $sibling_barcodes = array_values(array_unique($sibling_barcodes));
                            }
                        }
                    }
                }
                if (!empty($sibling_barcodes)) {
                    $out['sibling_barcodes'] = $sibling_barcodes;
                }
            } catch (Throwable $e) {
                // Still return the primary product; sibling discovery is optional
            }
        }
        auragold_json_out($out);
        exit;
    }
}

if (!$stock_check && !$allow_non_stock_barcode) {
    auragold_json_out([
        'success' => false,
        'message' => 'This barcode is not in stock at your branch.',
    ]);
    exit;
}

if ($allow_non_stock_barcode) {
// 2) Fallback: get product by barcode from tbl_product_characteristics (purchase / inward flows only)
    $gpb_pc_bsql = auragold_gpb_tbl_pc_branch_sql_fragment($conn, $working_branch_id);
$query = "
    SELECT 
        pc.*,
        p.id as product_id,
        p.name as product_name,
        p.article,
        p.alternate_name,
        m.display_name as metal_name,
        m.id as metal_id
    FROM tbl_product_characteristics pc
    LEFT JOIN tbl_products p ON pc.product_id = p.id
    LEFT JOIN tbl_metal m ON pc.metal_id = m.id
    WHERE pc.barcode = '$barcode_esc'
    AND pc.status = 1
    AND p.status = 1
    $gpb_pc_bsql
    ORDER BY pc.id DESC
    LIMIT 1
";

$result = getRecord($query);

if ($result) {
    $product_id = (int)$result['product_id'];
    $product = [
        'id' => $result['product_id'],
        'name' => $result['product_name'],
        'article' => $result['article'],
        'alternate_name' => $result['alternate_name'],
        'characteristic_id' => $result['id'],
        'barcode' => $result['barcode'],
        'metal_name' => $result['metal_name'],
        'metal_id' => $result['metal_id'],
        'opening_weight' => $result['opening_weight'],
        'opening_purity' => $result['opening_purity'],
        'opening_qty' => $result['opening_qty'],
        'final_weight' => $result['final_weight'],
        'rate' => $result['rate'],
        'value' => $result['value'],
        'purity' => $result['opening_purity'],
        'metal_qty' => 1,
        'metal_weight' => (float)($result['opening_weight'] ?? 0),
        'hsn' => $result['hsn'],
        'sku_code' => $result['sku_code'],
        'making_on' => $result['making_on'],
        'diamond_category' => $result['diamond_category'],
        'carat' => $result['carat'],
        'discount' => $result['discount'],
    ];
    $product = auragold_gpb_attach_gst_fields_to_product($conn, $product, $product_id, $working_branch_id > 0 ? $working_branch_id : null);
    $product = attach_latest_stock_journal_to_product($product, $barcode_esc, $conn);
    auragold_json_out(['success' => true, 'product' => $product]);
} else {
    // 3) Barcode exists only in stock journal (e.g. opening / not yet in tbl_stock)
    $sj_only = getRecord("SELECT * FROM tbl_stock_journal WHERE barcode = '$barcode_esc' AND status = 'active' ORDER BY id DESC LIMIT 1");
    if ($sj_only && !empty($sj_only['product_id'])) {
        $sj_pid = (int) $sj_only['product_id'];
        $sj_pcid = !empty($sj_only['product_characteristic_id']) ? (int) $sj_only['product_characteristic_id'] : null;
        if (auragold_sale_invoice_line_exists_for_barcode_product($barcode_esc, $sj_pid, $sj_pcid)) {
            auragold_json_out([
                'success' => false,
                'message' => 'Barcode no already sold',
            ]);
            exit;
        }
        $pid = (int)$sj_only['product_id'];
        $pcid = !empty($sj_only['product_characteristic_id']) ? (int)$sj_only['product_characteristic_id'] : null;
        $char_join = $pcid ? "LEFT JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.id = $pcid" : "LEFT JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.status = 1";
        $stock_product = getRecord("
            SELECT p.id as product_id, p.name as product_name, p.article, p.alternate_name,
                   pc.id as characteristic_id, pc.barcode as pc_barcode, pc.opening_weight as pc_opening_weight,
                   pc.opening_purity as pc_opening_purity, pc.opening_qty as pc_opening_qty,
                   pc.final_weight as pc_final_weight, pc.rate as pc_rate, pc.value as pc_value,
                   pc.hsn, pc.sku_code, pc.making_on, pc.diamond_category, pc.carat, pc.discount,
                   m.display_name as metal_name, m.id as metal_id
            FROM tbl_products p
            $char_join
            LEFT JOIN tbl_metal m ON (pc.metal_id = m.id)
            WHERE p.id = $pid AND p.status = 1
            ORDER BY pc.id DESC
            LIMIT 1
        ");
        if ($stock_product) {
            $product_id = (int)$stock_product['product_id'];
            $product = [
                'id' => $stock_product['product_id'],
                'name' => $stock_product['product_name'],
                'article' => $stock_product['article'],
                'alternate_name' => $stock_product['alternate_name'],
                'characteristic_id' => $stock_product['characteristic_id'] ?: $pcid,
                'barcode' => $barcode,
                'metal_name' => $stock_product['metal_name'],
                'metal_id' => $stock_product['metal_id'],
                'opening_weight' => $stock_product['pc_opening_weight'],
                'opening_purity' => $stock_product['pc_opening_purity'],
                'opening_qty' => $stock_product['pc_opening_qty'],
                'final_weight' => $stock_product['pc_final_weight'],
                'rate' => $stock_product['pc_rate'],
                'value' => $stock_product['pc_value'],
                'purity' => $stock_product['pc_opening_purity'],
                'metal_qty' => 1,
                'metal_weight' => (float)($stock_product['pc_opening_weight'] ?? 0),
                'hsn' => $stock_product['hsn'],
                'sku_code' => $stock_product['sku_code'],
                'making_on' => $stock_product['making_on'],
                'diamond_category' => $stock_product['diamond_category'],
                'carat' => $stock_product['carat'],
                'discount' => $stock_product['discount'],
                'stock_journal' => $sj_only,
            ];
            $product = auragold_gpb_attach_gst_fields_to_product($conn, $product, $product_id, $working_branch_id > 0 ? $working_branch_id : null);
            $product = attach_latest_stock_journal_to_product($product, $barcode_esc, $conn);
            auragold_json_out(['success' => true, 'product' => $product, 'source' => 'stock_journal']);
            exit;
        }
    }
    auragold_json_out([
        'success' => false,
        'message' => 'Product with barcode "' . htmlspecialchars($barcode) . '" not found',
    ]);
}
}
} catch (Throwable $e) {
    auragold_json_out([
        'success' => false,
        'message' => 'Barcode lookup failed.',
    ]);
}
