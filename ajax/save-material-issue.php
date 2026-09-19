<?php
session_start();
ob_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';

// Drop any accidental notices from includes so the client always gets pure JSON.
if (ob_get_length() !== false) {
    ob_clean();
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$sale_order_id = isset($_POST['sale_order_id']) ? (int)$_POST['sale_order_id'] : 0;
/** When 1, always INSERT a new tbl_material_issues row (do not reuse existing for same sale order). */
$force_new_jwo = isset($_POST['force_new_jwo']) && ($_POST['force_new_jwo'] === '1' || $_POST['force_new_jwo'] === 1 || $_POST['force_new_jwo'] === true);
$jwo_status = isset($_POST['jwo_status']) ? trim($_POST['jwo_status']) : 'Processing';
$department_id = (isset($_POST['department_id']) && $_POST['department_id'] !== '') ? (int)$_POST['department_id'] : null;
$priority = isset($_POST['priority']) ? trim($_POST['priority']) : 'Medium';
$jwo_id = isset($_POST['jwo_id']) ? (int)$_POST['jwo_id'] : 0;
$department_user_id_provided = isset($_POST['department_user_id']);
$department_user_id = null;
if ($department_user_id_provided) {
    $v_du = trim((string)$_POST['department_user_id']);
    $department_user_id = ($v_du !== '' && (int)$v_du > 0) ? (int)$v_du : null;
}

$sales_person_post = isset($_POST['sales_person']) ? trim((string)$_POST['sales_person']) : '';

$items_json = isset($_POST['items']) ? $_POST['items'] : '';
$items = [];
if (is_string($items_json) && $items_json !== '') {
    $items = json_decode($items_json, true);
}
if (!is_array($items)) {
    $items = [];
}

$payments_json_mi = isset($_POST['payments']) ? $_POST['payments'] : '';
$me_mi_payments = [];
if (is_string($payments_json_mi) && $payments_json_mi !== '') {
    $me_mi_payments = json_decode($payments_json_mi, true);
}
if (!is_array($me_mi_payments)) {
    $me_mi_payments = [];
}
if ($sale_order_id > 0) {
    require_once __DIR__ . '/../includes/auragold_material_issue_rows_for_sale_order.php';
    if (function_exists('auragold_material_issue_should_hide_payment_row')) {
        $me_mi_payments = array_values(array_filter($me_mi_payments, static function ($pay) use ($conn) {
            return is_array($pay) && !auragold_material_issue_should_hide_payment_row($conn, $pay);
        }));
    }
}
$metal_exchange_barcodes_out = [];

$standalone_mi = ($sale_order_id < 1);
$header_order_date = isset($_POST['header_order_date']) ? trim((string)$_POST['header_order_date']) : '';
$header_due_date = isset($_POST['header_due_date']) ? trim((string)$_POST['header_due_date']) : '';
$header_customer_name = isset($_POST['header_customer_name']) ? trim((string)$_POST['header_customer_name']) : '';

if (function_exists('auragold_parse_ui_date_to_ymd')) {
    $header_order_date = auragold_parse_ui_date_to_ymd($header_order_date);
    $header_due_date = auragold_parse_ui_date_to_ymd($header_due_date);
} else {
    // Fallback if date helper missing
    if ($header_order_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $header_order_date)) {
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $header_order_date, $hm)) {
            $header_order_date = sprintf('%04d-%02d-%02d', (int) $hm[3], (int) $hm[2], (int) $hm[1]);
        } else {
            $header_order_date = '';
        }
    }
    if ($header_due_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $header_due_date)) {
        if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/', $header_due_date, $hm)) {
            $header_due_date = sprintf('%04d-%02d-%02d', (int) $hm[3], (int) $hm[2], (int) $hm[1]);
        } else {
            $header_due_date = '';
        }
    }
}

$tbl_master = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issues'");
$tbl_items = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issue_items'");
if (!$tbl_master || mysqli_num_rows($tbl_master) === 0 || !$tbl_items || mysqli_num_rows($tbl_items) === 0) {
    if ($tbl_master) {
        mysqli_free_result($tbl_master);
    }
    if ($tbl_items) {
        mysqli_free_result($tbl_items);
    }
    echo json_encode(['status' => 'error', 'message' => 'Material issue tables not found. Please run admin/sql/create_tbl_material_issues.sql']);
    exit;
}
mysqli_free_result($tbl_master);
mysqli_free_result($tbl_items);

// Metal-exchange / stock helpers expect this column; missing it throws and breaks JSON AJAX.
if (is_file(dirname(__DIR__) . '/includes/auragold_material_issue_rows_for_sale_order.php')) {
    require_once dirname(__DIR__) . '/includes/auragold_material_issue_rows_for_sale_order.php';
}
if (function_exists('auragold_ensure_stock_source_stock_id_column')) {
    @auragold_ensure_stock_source_stock_id_column($conn);
}

$has_mi_branch   = auragold_ensure_table_branch_id_column($conn, 'tbl_material_issues');
$hdr_mi_branch   = auragold_transaction_header_branch_id();
$mi_scope_sql    = ($has_mi_branch && $hdr_mi_branch > 0) ? (' AND branch_id = ' . (int) $hdr_mi_branch) : '';

$mi_so_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_material_issues LIKE 'sale_order_id'");
if ($mi_so_col && mysqli_num_rows($mi_so_col) > 0) {
    $mi_cr = mysqli_fetch_assoc($mi_so_col);
    mysqli_free_result($mi_so_col);
    if (!empty($mi_cr['Null']) && strtoupper((string)$mi_cr['Null']) === 'NO') {
        @mysqli_query($conn, 'ALTER TABLE `tbl_material_issues` MODIFY COLUMN `sale_order_id` int(11) DEFAULT NULL');
    }
} elseif ($mi_so_col) {
    mysqli_free_result($mi_so_col);
}

$qc_mi_req = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_material_issue_items LIKE 'requested_purity'");
if (!$qc_mi_req || mysqli_num_rows($qc_mi_req) === 0) {
    if ($qc_mi_req) {
        mysqli_free_result($qc_mi_req);
    }
    @mysqli_query($conn, "ALTER TABLE `tbl_material_issue_items` ADD COLUMN `requested_purity` decimal(12,4) DEFAULT NULL AFTER `purity_weight`, ADD COLUMN `requested_wt` decimal(12,4) DEFAULT NULL AFTER `requested_purity`, ADD COLUMN `alloy_wt` decimal(12,4) DEFAULT NULL AFTER `requested_wt`");
} elseif ($qc_mi_req) {
    mysqli_free_result($qc_mi_req);
}

$map_chk_req = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
$has_department_user_map = ($map_chk_req && mysqli_num_rows($map_chk_req) > 0);
if ($map_chk_req) {
    mysqli_free_result($map_chk_req);
}

if ($has_department_user_map) {
    if (!$department_user_id_provided || $department_user_id === null || (int)$department_user_id < 1) {
        echo json_encode(['status' => 'error', 'message' => 'Name is required']);
        exit;
    }
}
if ($department_id !== null && (int)$department_id > 0) {
    $department_id = (int)$department_id;
} else {
    $department_id = null;
}

if (!$standalone_mi && $jwo_id < 1 && !$force_new_jwo) {
    $row_existing = getRecord("SELECT id FROM tbl_material_issues WHERE sale_order_id = $sale_order_id$mi_scope_sql LIMIT 1");
    if ($row_existing && !empty($row_existing['id'])) {
        $jwo_id = (int)$row_existing['id'];
    }
}

if ($standalone_mi) {
    $grand_from_items = 0;
    foreach ($items as $item) {
        $grand_from_items += (float)($item['net_amt_with_tax'] ?? $item['net_amount'] ?? 0);
    }
    $grand_from_items = round($grand_from_items, 2);
    $sale_order = [
        'id' => 0,
        'order_no' => '',
        'customer_name' => $header_customer_name,
        'order_date' => $header_order_date !== '' ? $header_order_date : null,
        'due_date' => $header_due_date !== '' ? $header_due_date : null,
        'grand_total' => $grand_from_items,
        'status' => '',
    ];
} else {
    $sale_order = getRecord("SELECT id, order_no, customer_name, order_date, due_date, grand_total, status FROM tbl_sale_orders WHERE id = $sale_order_id");
    if (!$sale_order) {
        echo json_encode(['status' => 'error', 'message' => 'Sale order not found']);
        exit;
    }
    if (auragold_tbl_has_column($conn, 'tbl_sale_orders', 'branch_id')) {
        $sob = getRecord('SELECT branch_id FROM tbl_sale_orders WHERE id = ' . (int) $sale_order_id . ' LIMIT 1');
        $sbi = (int) ($sob['branch_id'] ?? 0);
        $hdr = (int) $hdr_mi_branch;
        if ($sbi > 0 && $hdr > 0 && $sbi !== $hdr) {
            echo json_encode(['status' => 'error', 'message' => 'Sale order belongs to another branch.']);
            exit;
        }
    }
}

$cd_so = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'department_id'");
if ($cd_so && mysqli_num_rows($cd_so) === 0) {
    mysqli_free_result($cd_so);
    @mysqli_query($conn, "ALTER TABLE `tbl_sale_orders` ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `customer_name`");
} elseif ($cd_so) {
    mysqli_free_result($cd_so);
}

function auragold_sync_sale_order_department($conn, $sale_order_id, $department_id) {
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'department_id'");
    if (!$c || mysqli_num_rows($c) === 0) {
        if ($c) {
            mysqli_free_result($c);
        }
        return;
    }
    mysqli_free_result($c);
    $sid = (int)$sale_order_id;
    if ($department_id !== null && (int)$department_id > 0) {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET department_id = " . (int)$department_id . " WHERE id = $sid");
    } else {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET department_id = NULL WHERE id = $sid");
    }
}

function auragold_sync_sale_order_sales_person($conn, $sale_order_id, $sales_person) {
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'sales_person'");
    if (!$c || mysqli_num_rows($c) === 0) {
        if ($c) {
            mysqli_free_result($c);
        }
        return;
    }
    mysqli_free_result($c);
    $sid = (int)$sale_order_id;
    $sp = mysqli_real_escape_string($conn, $sales_person);
    if ($sales_person !== '') {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET sales_person = '$sp' WHERE id = $sid");
    } else {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET sales_person = NULL WHERE id = $sid");
    }
}

function auragold_material_issue_no_taken($conn, $no_esc, $branch_scope_sql = '') {
    $mi_active = auragold_doc_series_active_sql($conn, 'tbl_material_issues');
    $a = getRecord("SELECT id FROM tbl_material_issues WHERE material_issue_no = '$no_esc' $branch_scope_sql" . $mi_active . " LIMIT 1");
    if ($a) {
        return true;
    }
    $tr = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_issues'");
    if ($tr && mysqli_num_rows($tr) > 0) {
        mysqli_free_result($tr);
        $rmi_active = auragold_doc_series_active_sql($conn, 'tbl_repair_material_issues');
        $b = getRecord("SELECT id FROM tbl_repair_material_issues WHERE material_issue_no = '$no_esc'" . $rmi_active . " LIMIT 1");
        return (bool)$b;
    }
    if ($tr) {
        mysqli_free_result($tr);
    }
    return false;
}

/** Metal (final) weight for issue rules: final_weight, else net, else gross. */
function auragold_material_issue_line_metal_wt(array $item): float
{
    $fw = (float) ($item['final_weight'] ?? 0);
    if ($fw > 0.0000001) {
        return $fw;
    }
    $nw = (float) ($item['net_weight'] ?? 0);
    if ($nw > 0.0000001) {
        return $nw;
    }

    return (float) ($item['gross_weight'] ?? 0);
}

/**
 * Weight issued to department / deducted from available stock:
 * both metal and requested filled → requested; only requested → requested; otherwise metal when present.
 */
function auragold_material_issue_resolve_issue_weight(array $item): float
{
    $metal = auragold_material_issue_line_metal_wt($item);
    $req = (float) ($item['requested_wt'] ?? 0);
    $has_m = $metal > 0.0000001;
    $has_r = $req > 0.0000001;
    if ($has_m && $has_r) {
        return $req;
    }
    if ($has_r) {
        return $req;
    }
    if ($has_m) {
        return $metal;
    }

    return 0.0;
}

function auragold_material_issue_tbl_stock_has_reference(mysqli $conn): bool
{
    static $cached_ok = null;
    if ($cached_ok === true) {
        return true;
    }
    $ref_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
    $n = ($ref_check && mysqli_num_rows($ref_check) >= 2);
    if ($ref_check) {
        mysqli_free_result($ref_check);
    }
    if ($n) {
        $cached_ok = true;

        return true;
    }
    @mysqli_query($conn, 'ALTER TABLE `tbl_stock` ADD COLUMN `reference_id` INT NULL DEFAULT NULL AFTER `transaction_date`');
    @mysqli_query($conn, 'ALTER TABLE `tbl_stock` ADD COLUMN `reference_type` VARCHAR(50) NULL DEFAULT NULL AFTER `reference_id`');
    $ref_check2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
    $ok = ($ref_check2 && mysqli_num_rows($ref_check2) >= 2);
    if ($ref_check2) {
        mysqli_free_result($ref_check2);
    }
    if ($ok) {
        $cached_ok = true;
    }

    return $ok;
}

/** Restore main stock from prior Material Issue outward rows, then delete those outward rows. */
function auragold_material_issue_reverse_outward_stock(mysqli $conn, int $mi_header_id): void
{
    if (!auragold_material_issue_tbl_stock_has_reference($conn) || $mi_header_id < 1) {
        return;
    }
    $mid = (int) $mi_header_id;
    $rev_rows = getList("SELECT barcode, opening_weight, opening_qty, product_id, product_characteristic_id, branch_id FROM tbl_stock WHERE stock_type = 'outward' AND reference_id = $mid AND reference_type = 'material_issue'");
    if (!is_array($rev_rows)) {
        $rev_rows = [];
    }
    foreach ($rev_rows as $rv) {
        $ow = (float) ($rv['opening_weight'] ?? 0);
        $oq = (float) ($rv['opening_qty'] ?? 0);
        if ($ow <= 0 && $oq <= 0) {
            continue;
        }
        $b = trim((string) ($rv['barcode'] ?? ''));
        $target_id = 0;
        if ($b !== '') {
            $be = mysqli_real_escape_string($conn, $b);
            $tr = getRecord("SELECT id FROM tbl_stock WHERE barcode = '$be' AND status = 1 AND stock_type IN ('inward','balance','opening','purchase','stock_journal') ORDER BY id DESC LIMIT 1");
            if ($tr && !empty($tr['id'])) {
                $target_id = (int) $tr['id'];
            }
        }
        if ($target_id <= 0) {
            $pid = (int) ($rv['product_id'] ?? 0);
            $bid = (int) ($rv['branch_id'] ?? 0);
            if ($pid > 0 && $bid > 0) {
                $cid_raw = $rv['product_characteristic_id'] ?? null;
                if ($cid_raw !== null && $cid_raw !== '') {
                    $cid = (int) $cid_raw;
                    $tr = getRecord("SELECT id FROM tbl_stock WHERE product_id = $pid AND product_characteristic_id = $cid AND branch_id = $bid AND status = 1 AND stock_type IN ('inward','balance','opening','purchase','stock_journal') ORDER BY id DESC LIMIT 1");
                } else {
                    $tr = getRecord("SELECT id FROM tbl_stock WHERE product_id = $pid AND product_characteristic_id IS NULL AND branch_id = $bid AND status = 1 AND stock_type IN ('inward','balance','opening','purchase','stock_journal') ORDER BY id DESC LIMIT 1");
                }
                if ($tr && !empty($tr['id'])) {
                    $target_id = (int) $tr['id'];
                }
            }
        }
        if ($target_id > 0) {
            mysqli_query($conn, "UPDATE tbl_stock SET current_weight = COALESCE(current_weight, 0) + $ow, current_qty = COALESCE(current_qty, 0) + $oq WHERE id = $target_id");
        }
    }
    mysqli_query($conn, "DELETE FROM tbl_stock WHERE stock_type = 'outward' AND reference_id = $mid AND reference_type = 'material_issue'");
}

/**
 * Deduct issue_weight + quantity from barcode inward stock; insert outward row linked to Material Issue.
 * Mirrors ajax/save-consignment-out.php (barcode path).
 *
 * @return bool false on hard SQL failure
 */
function auragold_material_issue_apply_barcode_stock_deduct(
    mysqli $conn,
    int $mi_header_id,
    string $transaction_date_ymd,
    array $item,
    float $issue_weight
): bool {
    if ($mi_header_id < 1 || $issue_weight <= 0.0000001) {
        return true;
    }
    $bc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'barcode'");
    $tbl_stock_has_barcode = ($bc && mysqli_num_rows($bc) > 0);
    if ($bc) {
        mysqli_free_result($bc);
    }
    if (!$tbl_stock_has_barcode) {
        return true;
    }
    $has_ref = auragold_material_issue_tbl_stock_has_reference($conn);
    if (!$has_ref) {
        return true;
    }

    // Include 'inward' — some lots are stored as inward/balance rows; omitting them skipped MI outward + stock history.
    $stock_in_types = "'opening','purchase','stock_journal','balance','sale_return','inward'";
    $co_branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
    if ($co_branch_id <= 0 && !empty($_SESSION['working_branch_id'])) {
        $co_branch_id = (int) $_SESSION['working_branch_id'];
    } elseif ($co_branch_id <= 0 && !empty($_SESSION['branch_id'])) {
        $co_branch_id = (int) $_SESSION['branch_id'];
    }
    $co_branch_sql = '';
    if ($co_branch_id > 0 && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock', 'branch_id')) {
        $co_main_bid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($co_main_bid > 0 && $co_branch_id === $co_main_bid) {
            $co_branch_sql = ' AND (branch_id = ' . $co_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        } else {
            $co_branch_sql = ' AND branch_id = ' . $co_branch_id;
        }
    }

    $barcode_raw = trim((string) ($item['barcode'] ?? ''));
    $deduct_weight = $issue_weight;
    $quantity = (float) ($item['quantity'] ?? 1);
    if ($quantity <= 0) {
        $quantity = 1;
    }
    $product_id = (int) ($item['product_id'] ?? 0);
    $characteristic_id = isset($item['characteristic_id']) && $item['characteristic_id'] !== '' ? (int) $item['characteristic_id'] : 0;
    $metal_id = (int) ($item['metal_id'] ?? 0);
    if ($metal_id <= 0 && $characteristic_id > 0) {
        $mr_pc = getRecord('SELECT metal_id FROM tbl_product_characteristics WHERE id = ' . (int) $characteristic_id . ' AND status = 1 LIMIT 1');
        $metal_id = (int) ($mr_pc['metal_id'] ?? 0);
    }
    $purity = (float) ($item['purity'] ?? 0);
    $rate = (float) ($item['rate'] ?? 0);

    $source_stock = null;
    if ($barcode_raw !== '') {
        $barcode_esc_q = mysqli_real_escape_string($conn, $barcode_raw);
        $source_stock = getRecord("
            SELECT *
            FROM tbl_stock
            WHERE barcode = '$barcode_esc_q' AND status = 1
            AND stock_type IN ($stock_in_types)
            AND (
                COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0
                OR COALESCE(opening_qty, 0) > 0 OR COALESCE(opening_weight, 0) > 0
            )
            $co_branch_sql
            ORDER BY CASE
                WHEN COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0 THEN 0
                ELSE 1
            END, id DESC
            LIMIT 1
        ");
        if (!$source_stock) {
            $co_branch_sql_s = str_replace('branch_id', 's.branch_id', $co_branch_sql);
            $agg_pick = getRecord("
                SELECT MAX(CASE WHEN s.stock_type IN ($stock_in_types) THEN s.id END) AS pick_id
                FROM tbl_stock s
                WHERE s.status = 1 AND s.barcode = '$barcode_esc_q'
                $co_branch_sql_s
                GROUP BY s.barcode, s.branch_id
                HAVING MAX(CASE WHEN s.stock_type IN ($stock_in_types) THEN s.id END) IS NOT NULL
                AND (
                    (SUM(CASE WHEN s.stock_type IN ($stock_in_types) THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)
                     - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)) > 0.00001
                    OR
                    (SUM(CASE WHEN s.stock_type IN ($stock_in_types) THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)
                     - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)) > 0.00001
                )
                LIMIT 1
            ");
            if ($agg_pick && !empty($agg_pick['pick_id'])) {
                $pick_id = (int) $agg_pick['pick_id'];
                if ($pick_id > 0) {
                    $source_stock = getRecord("SELECT * FROM tbl_stock WHERE id = $pick_id AND status = 1 LIMIT 1");
                }
            }
        }
        if (!$source_stock && $co_branch_sql !== '') {
            $source_stock = getRecord("
                SELECT *
                FROM tbl_stock
                WHERE barcode = '$barcode_esc_q' AND status = 1
                AND stock_type IN ($stock_in_types)
                AND (
                    COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0
                    OR COALESCE(opening_qty, 0) > 0 OR COALESCE(opening_weight, 0) > 0
                )
                ORDER BY CASE
                    WHEN COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0 THEN 0
                    ELSE 1
                END, id DESC
                LIMIT 1
            ");
        }
    } elseif ($product_id > 0 && ($characteristic_id > 0 || $metal_id > 0)) {
        // Material Issue UI often clears barcode in JSON; resolve lot by product + characteristic + metal (same branch rules).
        $pc_sql = '';
        if ($characteristic_id > 0) {
            $pc_sql = ' AND product_characteristic_id = ' . (int) $characteristic_id;
        }
        $metal_sql = $metal_id > 0 ? ' AND metal_id = ' . (int) $metal_id : '';
        $source_stock = getRecord("
            SELECT *
            FROM tbl_stock
            WHERE status = 1
            AND stock_type IN ($stock_in_types)
            AND product_id = " . (int) $product_id . "
            $pc_sql
            $metal_sql
            AND (
                COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0
                OR COALESCE(opening_qty, 0) > 0 OR COALESCE(opening_weight, 0) > 0
            )
            $co_branch_sql
            ORDER BY CASE
                WHEN COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0 THEN 0
                ELSE 1
            END, id DESC
            LIMIT 1
        ");
        if (!$source_stock && $co_branch_sql !== '') {
            $source_stock = getRecord("
                SELECT *
                FROM tbl_stock
                WHERE status = 1
                AND stock_type IN ($stock_in_types)
                AND product_id = " . (int) $product_id . "
                $pc_sql
                $metal_sql
                AND (
                    COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0
                    OR COALESCE(opening_qty, 0) > 0 OR COALESCE(opening_weight, 0) > 0
                )
                ORDER BY CASE
                    WHEN COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0 THEN 0
                    ELSE 1
                END, id DESC
                LIMIT 1
            ");
        }
    }

    if (!$source_stock) {
        $log_key = $barcode_raw !== '' ? ('barcode ' . $barcode_raw) : ('product ' . $product_id . ' pc ' . $characteristic_id . ' metal ' . $metal_id);
        error_log('Material Issue: no inward stock row for ' . $log_key);

        return true;
    }

    $stock_row = $source_stock;
    if ($product_id <= 0) {
        $product_id = (int) ($stock_row['product_id'] ?? 0);
    }
    if ($characteristic_id <= 0 && isset($stock_row['product_characteristic_id']) && $stock_row['product_characteristic_id'] !== null && $stock_row['product_characteristic_id'] !== '') {
        $characteristic_id = (int) $stock_row['product_characteristic_id'];
    }
    if ($metal_id <= 0) {
        $metal_id = (int) ($stock_row['metal_id'] ?? 0);
    }
    $branch_id = (int) ($stock_row['branch_id'] ?? 0);
    $stock_metal_id = (int) ($stock_row['metal_id'] ?? $metal_id);
    if ($stock_metal_id <= 0) {
        $stock_metal_id = $metal_id > 0 ? $metal_id : 1;
    }
    $stock_purity = (float) ($stock_row['opening_purity'] ?? $purity);
    $stock_rate_val = (float) ($stock_row['rate'] ?? $rate);
    $stock_value = $deduct_weight * $stock_rate_val;
    $barcode_for_out = $barcode_raw !== '' ? $barcode_raw : trim((string) ($stock_row['barcode'] ?? ''));
    $barcode_esc = mysqli_real_escape_string($conn, $barcode_for_out);

    $td = $transaction_date_ymd;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $td)) {
        $td = date('Y-m-d');
    }
    $td_esc = mysqli_real_escape_string($conn, $td);

    $outward_sql = "
        INSERT INTO tbl_stock (
            product_id, product_characteristic_id, barcode, branch_id, metal_id,
            opening_weight, opening_purity, opening_qty, final_weight, rate, value,
            current_weight, current_qty, stock_type, transaction_date, status, created_at,
            reference_id, reference_type
        ) VALUES (
            " . ($product_id > 0 ? $product_id : 'NULL') . ",
            " . ($characteristic_id > 0 ? $characteristic_id : 'NULL') . ",
            '$barcode_esc',
            $branch_id,
            $stock_metal_id,
            $deduct_weight,
            $stock_purity,
            $quantity,
            $deduct_weight,
            $stock_rate_val,
            $stock_value,
            $deduct_weight,
            $quantity,
            'outward',
            '$td_esc',
            1,
            NOW(),
            $mi_header_id,
            'material_issue'
        )
    ";
    if (!@mysqli_query($conn, $outward_sql)) {
        error_log('Material Issue outward insert failed: ' . mysqli_error($conn));

        return false;
    }

    $src_id = (int) $stock_row['id'];
    $prev_cq = (float) ($stock_row['current_qty'] ?? 0);
    $prev_cw = (float) ($stock_row['current_weight'] ?? 0);
    $op_q = (float) ($stock_row['opening_qty'] ?? 0);
    $op_w = (float) ($stock_row['opening_weight'] ?? 0);
    $sold_q = $quantity;

    if ($prev_cq > 0 || $prev_cw > 0) {
        if ($sold_q <= 0 && $prev_cw > 0 && $prev_cq > 0) {
            $sold_q = $prev_cq * ($deduct_weight / $prev_cw);
        }
        $balance_weight = $prev_cw - $deduct_weight;
        $new_cq = max(0, $prev_cq - $sold_q);
        if ($balance_weight <= 0) {
            if (!@mysqli_query($conn, "UPDATE tbl_stock SET current_weight = 0, current_qty = 0, value = 0 WHERE id = $src_id")) {
                return false;
            }
        } else {
            $new_val = $stock_rate_val * $balance_weight;
            if (!@mysqli_query($conn, "UPDATE tbl_stock SET current_weight = $balance_weight, current_qty = $new_cq, final_weight = $balance_weight, value = $new_val WHERE id = $src_id")) {
                return false;
            }
        }
    } else {
        $new_op_q = max(0, $op_q - $sold_q);
        $new_op_w = max(0, $op_w - $deduct_weight);
        if ($new_op_w <= 0.00001 && $new_op_q <= 0.00001) {
            if (!@mysqli_query($conn, "UPDATE tbl_stock SET opening_qty = 0, opening_weight = 0, final_weight = 0, value = 0 WHERE id = $src_id")) {
                return false;
            }
        } else {
            $new_val = $stock_rate_val * $new_op_w;
            if (!@mysqli_query($conn, "UPDATE tbl_stock SET opening_qty = $new_op_q, opening_weight = $new_op_w, final_weight = $new_op_w, value = $new_val WHERE id = $src_id")) {
                return false;
            }
        }
    }

    return true;
}

$sale_order_no = mysqli_real_escape_string($conn, $sale_order['order_no']);
$customer_name = mysqli_real_escape_string($conn, $sale_order['customer_name'] ?? '');
$order_date = !empty($sale_order['order_date']) ? mysqli_real_escape_string($conn, $sale_order['order_date']) : 'NULL';
$due_date = !empty($sale_order['due_date']) ? "'" . mysqli_real_escape_string($conn, $sale_order['due_date']) . "'" : 'NULL';
$grand_total = (float)($sale_order['grand_total'] ?? 0);

if ($jwo_id > 0) {
    $jwo = getRecord("SELECT id, sale_order_id, status, department_id, department_user_id FROM tbl_material_issues WHERE id = $jwo_id");
    if (!$jwo) {
        echo json_encode(['status' => 'error', 'message' => 'Material issue not found']);
        exit;
    }
    try {
        auragold_branch_require_document_access($conn, 'tbl_material_issues', $jwo_id);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    $jwo_so_stored = isset($jwo['sale_order_id']) && $jwo['sale_order_id'] !== '' && $jwo['sale_order_id'] !== null ? (int)$jwo['sale_order_id'] : 0;
    $post_so_cmp = $standalone_mi ? 0 : (int)$sale_order_id;
    if ($jwo_so_stored !== $post_so_cmp) {
        echo json_encode(['status' => 'error', 'message' => 'Sale order mismatch']);
        exit;
    }
    $new_status = mysqli_real_escape_string($conn, $jwo_status);
    $grand_total = 0;
    foreach ($items as $item) {
        $grand_total += (float)($item['net_amt_with_tax'] ?? $item['net_amount'] ?? 0);
    }
    $grand_total = round($grand_total, 2);
    $upd = "UPDATE tbl_material_issues SET status = '$new_status', grand_total = $grand_total, updated_at = NOW()";
    if ($department_id !== null && $department_id > 0) {
        $upd .= ", department_id = " . (int)$department_id;
    }
    $priority_esc = mysqli_real_escape_string($conn, $priority);
    $upd .= ", priority = '$priority_esc'";
    if ($department_user_id_provided) {
        if ($department_user_id !== null && $department_user_id > 0) {
            $upd .= ", department_user_id = " . (int)$department_user_id;
        } else {
            $upd .= ", department_user_id = NULL";
        }
    }
    if ($standalone_mi) {
        $hdr_cn_esc = mysqli_real_escape_string($conn, $header_customer_name);
        $upd .= ", customer_name = '$hdr_cn_esc'";
        if ($header_order_date !== '') {
            $upd .= ", order_date = '" . mysqli_real_escape_string($conn, $header_order_date) . "'";
        }
        if ($header_due_date !== '') {
            $upd .= ", due_date = '" . mysqli_real_escape_string($conn, $header_due_date) . "'";
        }
    }
    if ($has_mi_branch && $hdr_mi_branch > 0) {
        $rb = getRecord('SELECT branch_id FROM tbl_material_issues WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
        if ($rb && (int) ($rb['branch_id'] ?? 0) <= 0) {
            $upd .= ', branch_id = ' . (int) $hdr_mi_branch;
        }
    }
    $upd .= " WHERE id = $jwo_id";
    try {
        if (!mysqli_query($conn, $upd)) {
            throw new RuntimeException(mysqli_error($conn) ?: 'Failed to update material issue');
        }
        auragold_material_issue_reverse_outward_stock($conn, (int) $jwo_id);
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=mi|hid=" . (int) $jwo_id . "|%'");
        mysqli_query($conn, "DELETE FROM tbl_material_issue_items WHERE material_issue_id = $jwo_id");
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    try {
    foreach ($items as $item) {
        $product_id = (int)($item['product_id'] ?? 0);
        $characteristic_id = isset($item['characteristic_id']) && $item['characteristic_id'] !== '' ? (int)$item['characteristic_id'] : null;
        $barcode = mysqli_real_escape_string($conn, $item['barcode'] ?? '');
        $product_name = mysqli_real_escape_string($conn, $item['product_name'] ?? '');
        $design_no = mysqli_real_escape_string($conn, $item['design_no'] ?? '');
        $carat = mysqli_real_escape_string($conn, $item['carat'] ?? '');
        $quantity = (float)($item['quantity'] ?? 1);
        $gross_weight = (float)($item['gross_weight'] ?? 0);
        $less_weight = (float)($item['less_weight'] ?? 0);
        $purity = (float)($item['purity'] ?? 0);
        $purity_weight = (float)($item['purity_weight'] ?? 0);
        $requested_purity = (float)($item['requested_purity'] ?? 0);
        $requested_wt = (float)($item['requested_wt'] ?? 0);
        $alloy_wt = (float)($item['alloy_wt'] ?? 0);
        $final_weight = (float)($item['final_weight'] ?? 0);
        $net_weight = (float)($item['net_weight'] ?? 0);
        $pure_weight = (float)($item['pure_weight'] ?? 0);
        $rate = (float)($item['rate'] ?? 0);
        $making_amount = (float)($item['making_amount'] ?? 0);
        $amount = (float)($item['amount'] ?? 0);
        $tax_amount = (float)($item['tax'] ?? 0);
        $net_amount = (float)($item['net_amount'] ?? 0);
        $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);
        $char_sql = $characteristic_id !== null ? $characteristic_id : 'NULL';
        $barcode_sql = $barcode !== '' ? "'$barcode'" : 'NULL';
        $design_sql = $design_no !== '' ? "'$design_no'" : 'NULL';
        $carat_sql = $carat !== '' ? "'$carat'" : 'NULL';
        $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_material_issue_items', $item);
        $ins_item = "INSERT INTO tbl_material_issue_items (material_issue_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, requested_purity, requested_wt, alloy_wt, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax{$ef_parts['columns']}, status, created_at) VALUES ($jwo_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $requested_purity, $requested_wt, $alloy_wt, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax{$ef_parts['values']}, 1, NOW())";
        mysqli_query($conn, $ins_item);
        $mi_line_id = (int) mysqli_insert_id($conn);
        require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
        $mih_now = getRecord("SELECT material_issue_no, order_date FROM tbl_material_issues WHERE id = " . (int) $jwo_id . " LIMIT 1");
        $mi_doc_now = trim((string) ($mih_now['material_issue_no'] ?? ''));
        $mi_dt_now = substr(trim((string) ($mih_now['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mi_dt_now)) {
            $mi_dt_now = $header_order_date !== '' ? substr($header_order_date, 0, 10) : '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mi_dt_now) && !empty($sale_order['order_date'])) {
                $mi_dt_now = substr(trim((string) $sale_order['order_date']), 0, 10);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mi_dt_now)) {
                $mi_dt_now = date('Y-m-d');
            }
        }
        $issue_wt_row = auragold_material_issue_resolve_issue_weight(array_merge($item, [
            'final_weight' => $final_weight,
            'net_weight' => $net_weight,
            'gross_weight' => $gross_weight,
            'requested_wt' => $requested_wt,
        ]));
        $metal_id_line = (int) ($item['metal_id'] ?? 0);
        if ($metal_id_line <= 0 && $characteristic_id !== null && (int) $characteristic_id > 0) {
            $mr_ln = getRecord('SELECT metal_id FROM tbl_product_characteristics WHERE id = ' . (int) $characteristic_id . ' AND status = 1 LIMIT 1');
            $metal_id_line = (int) ($mr_ln['metal_id'] ?? 0);
        }
        auragold_material_issue_apply_barcode_stock_deduct($conn, (int) $jwo_id, $mi_dt_now, array_merge($item, [
            'product_id' => $product_id,
            'characteristic_id' => $characteristic_id,
            'metal_id' => $metal_id_line,
        ]), $issue_wt_row);
        $metal_base = auragold_material_issue_line_metal_wt(array_merge($item, [
            'final_weight' => $final_weight,
            'net_weight' => $net_weight,
            'gross_weight' => $gross_weight,
        ]));
        $audit_pw = $purity_weight;
        if ($issue_wt_row > 0.0000001 && $metal_base > 0.0000001 && abs($issue_wt_row - $metal_base) > 0.0000001 && $purity_weight > 0.0000001) {
            $audit_pw = round($purity_weight * ($issue_wt_row / $metal_base), 4);
        }
        auragold_stock_history_audit_for_document_barcode_line($conn, 'Material Issue', $mi_doc_now, $mi_dt_now, 'MI', (int) $jwo_id, $mi_line_id, 'mi', array_merge($item, [
            'product_id' => $product_id,
            'product_characteristic_id' => $characteristic_id !== null ? (int) $characteristic_id : 0,
            'final_weight' => $issue_wt_row,
            'purity_weight' => $audit_pw,
            'gross_weight' => $issue_wt_row > 0.0000001 ? $issue_wt_row : ((float) ($item['gross_weight'] ?? 0)),
            'net_weight' => $issue_wt_row > 0.0000001 ? $issue_wt_row : ((float) ($item['net_weight'] ?? 0)),
        ]));
    }
    if (!$standalone_mi && $sale_order_id > 0 && strtolower($new_status) === 'completed') {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET status = 'completed', updated_at = NOW() WHERE id = $sale_order_id");
    }
    if (!$standalone_mi && $sale_order_id > 0) {
        auragold_sync_sale_order_department($conn, $sale_order_id, $department_id);
        auragold_sync_sale_order_sales_person($conn, $sale_order_id, $sales_person_post);
    }
    require_once __DIR__ . '/../includes/auragold_material_issue_rows_for_sale_order.php';
    $me_mi_stock_payments = function_exists('auragold_material_issue_payments_for_mi_stock_save')
        ? auragold_material_issue_payments_for_mi_stock_save($conn, $me_mi_payments)
        : $me_mi_payments;
    if ((int) $jwo_id > 0 && function_exists('auragold_material_issue_delete_client_added_me_stock')) {
        auragold_material_issue_delete_client_added_me_stock($conn, (int) $jwo_id);
    }
    if (!empty($me_mi_stock_payments)) {
        try {
            foreach ($me_mi_stock_payments as $__pmi) {
                if (!is_array($__pmi)) {
                    continue;
                }
                $__mmi = auragold_payment_merge_stored_details($__pmi);
                if (!auragold_payment_is_metal_exchange_inward($conn, $__mmi)) {
                    continue;
                }
                auragold_validate_metal_exchange_for_stock($conn, $__mmi);
            }
            $rmi_h_me = @getRecord('SELECT material_issue_no, order_date FROM tbl_material_issues WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
            $__mi_no_me = trim((string) ($rmi_h_me['material_issue_no'] ?? ''));
            $__mi_dt_me = substr(trim((string) ($rmi_h_me['order_date'] ?? '')), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__mi_dt_me)) {
                $__mi_dt_me = $standalone_mi ? ($header_order_date !== '' ? substr($header_order_date, 0, 10) : date('Y-m-d')) : (!empty($sale_order['order_date']) ? substr(trim((string) $sale_order['order_date']), 0, 10) : date('Y-m-d'));
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__mi_dt_me)) {
                    $__mi_dt_me = date('Y-m-d');
                }
            }
            $___mi_doc_me_ref = auragold_metal_exchange_document_init($conn, false, (int) $jwo_id, 'material_issue_metal_exchange');
            $seq_row = getRecord(
                "SELECT COUNT(*) AS c FROM tbl_stock WHERE status = 1"
                . " AND reference_type = 'material_issue_metal_exchange' AND reference_id = " . (int) $jwo_id
            );
            $pay_seq = (int) ($seq_row['c'] ?? 0);
            $me_reserved_bc = [];
            $me_reserved_stock_ids = [];
            foreach ($me_mi_stock_payments as $payment) {
                if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                    continue;
                }
                $___pm_mi = auragold_payment_merge_stored_details($payment);
                auragold_post_metal_exchange_payment_to_stock(
                    $conn,
                    'material_issue_metal_exchange',
                    (int) $jwo_id,
                    $__mi_no_me,
                    $__mi_dt_me,
                    $___pm_mi,
                    auragold_metal_exchange_default_branch_id(),
                    $pay_seq,
                    $___mi_doc_me_ref,
                    'Material Issue — Metal Exchange',
                    'mi_me',
                    'MI-ME-',
                    $metal_exchange_barcodes_out,
                    $me_reserved_bc,
                    $me_reserved_stock_ids
                );
                foreach ($metal_exchange_barcodes_out as $__me_bc_row) {
                    $__me_bc = trim((string) ($__me_bc_row['barcode'] ?? ''));
                    if ($__me_bc !== '' && !in_array($__me_bc, $me_reserved_bc, true)) {
                        $me_reserved_bc[] = $__me_bc;
                    }
                    $__me_sid = (int) ($__me_bc_row['stock_id'] ?? 0);
                    if ($__me_sid > 0 && !in_array($__me_sid, $me_reserved_stock_ids, true)) {
                        $me_reserved_stock_ids[] = $__me_sid;
                    }
                }
                $pay_seq++;
            }
        } catch (Throwable $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            exit;
        }
    }
    require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
    $rmi_apply_upd = @getRecord('SELECT material_issue_no, order_date FROM tbl_material_issues WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
    if ($rmi_apply_upd && (int) $jwo_id > 0) {
        $od_ap = substr(trim((string) ($rmi_apply_upd['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od_ap)) {
            $od_ap = date('Y-m-d');
        }
        auragold_voucher_apply_pending_diamond_stone_from_post(
            $conn,
            'material_issue',
            (int) $jwo_id,
            trim((string) ($rmi_apply_upd['material_issue_no'] ?? '')),
            $od_ap
        );
    }
    require_once __DIR__ . '/../includes/auragold_notifications.php';
    $rmi = @getRecord('SELECT material_issue_no, customer_name, order_date, due_date FROM tbl_material_issues WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
    if (is_array($rmi)) {
        $dd = isset($rmi['due_date']) && $rmi['due_date'] !== null && trim((string) $rmi['due_date']) !== ''
            ? substr(trim((string) $rmi['due_date']), 0, 10) : '';
        $od = substr(trim((string) ($rmi['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od)) {
            $od = date('Y-m-d');
        }
        auragold_notify_document_saved($conn, [
            'label' => 'Material Issue',
            'verb' => 'updated',
            'number' => trim((string) ($rmi['material_issue_no'] ?? '')),
            'party' => trim((string) ($rmi['customer_name'] ?? '')),
            'doc_date' => $od,
            'due_date' => $dd,
            'ref_id' => (int) $jwo_id,
        ]);
    }
    echo json_encode(['status' => 'success', 'message' => 'Material issue updated', 'jwo_id' => $jwo_id, 'new_barcodes' => $metal_exchange_barcodes_out]);
    exit;
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

if (count($items) === 1) {
    $grand_total = 0;
    foreach ($items as $item) {
        $grand_total += (float)($item['net_amt_with_tax'] ?? $item['net_amount'] ?? 0);
    }
    $grand_total = round($grand_total, 2);
}

$cfg_mi = function_exists('getMaterialIssueBillSeriesConfig')
    ? getMaterialIssueBillSeriesConfig($conn)
    : ['prefix' => 'MI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
$material_issue_no = function_exists('getNextMaterialIssueNo') ? getNextMaterialIssueNo($conn) : 'MI-1';
$material_issue_no_esc = mysqli_real_escape_string($conn, $material_issue_no);
$guard_no = 0;
while (auragold_material_issue_no_taken($conn, $material_issue_no_esc, $mi_scope_sql) && $guard_no < 5000) {
    $material_issue_no = function_exists('bumpMaterialIssueNo') ? bumpMaterialIssueNo($conn, $material_issue_no, $cfg_mi) : ($material_issue_no . '-1');
    $material_issue_no_esc = mysqli_real_escape_string($conn, $material_issue_no);
    $guard_no++;
}
if (function_exists('auragold_release_soft_deleted_document_no')) {
    auragold_release_soft_deleted_document_no($conn, [
        ['tbl_material_issues', 'material_issue_no'],
        ['tbl_repair_material_issues', 'material_issue_no'],
    ], $material_issue_no);
}

$status_esc = mysqli_real_escape_string($conn, $jwo_status);
$sid_sql = $standalone_mi ? 'NULL' : (string)(int)$sale_order_id;
$ins_master = "INSERT INTO tbl_material_issues (material_issue_no, sale_order_id, sale_order_no, customer_name, order_date, due_date, grand_total, status, "
    . ($has_mi_branch ? 'branch_id, ' : '')
    . "created_at) VALUES ('$material_issue_no_esc', $sid_sql, '$sale_order_no', '$customer_name', " . ($order_date !== 'NULL' ? "'$order_date'" : 'NULL') . ", $due_date, $grand_total, '$status_esc', "
    . ($has_mi_branch ? ((int) $hdr_mi_branch > 0 ? (int) $hdr_mi_branch : 'NULL') . ', ' : '')
    . 'NOW())';
if (!mysqli_query($conn, $ins_master)) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to create material issue: ' . mysqli_error($conn)]);
    exit;
}

$new_jwo_id = mysqli_insert_id($conn);

if ($department_id !== null && $department_id > 0) {
    mysqli_query($conn, "UPDATE tbl_material_issues SET department_id = " . (int)$department_id . " WHERE id = $new_jwo_id");
}
$priority_esc = mysqli_real_escape_string($conn, $priority);
mysqli_query($conn, "UPDATE tbl_material_issues SET priority = '$priority_esc' WHERE id = $new_jwo_id");
if ($department_user_id_provided) {
    if ($department_user_id !== null && $department_user_id > 0) {
        mysqli_query($conn, "UPDATE tbl_material_issues SET department_user_id = " . (int)$department_user_id . " WHERE id = $new_jwo_id");
    } else {
        mysqli_query($conn, "UPDATE tbl_material_issues SET department_user_id = NULL WHERE id = $new_jwo_id");
    }
}

foreach ($items as $item) {
    $product_id = (int)($item['product_id'] ?? 0);
    $characteristic_id = isset($item['characteristic_id']) && $item['characteristic_id'] !== '' ? (int)$item['characteristic_id'] : null;
    $barcode = mysqli_real_escape_string($conn, $item['barcode'] ?? '');
    $product_name = mysqli_real_escape_string($conn, $item['product_name'] ?? '');
    $design_no = mysqli_real_escape_string($conn, $item['design_no'] ?? '');
    $carat = mysqli_real_escape_string($conn, $item['carat'] ?? '');
    $quantity = (float)($item['quantity'] ?? 1);
    $gross_weight = (float)($item['gross_weight'] ?? 0);
    $less_weight = (float)($item['less_weight'] ?? 0);
    $purity = (float)($item['purity'] ?? 0);
    $purity_weight = (float)($item['purity_weight'] ?? 0);
    $requested_purity = (float)($item['requested_purity'] ?? 0);
    $requested_wt = (float)($item['requested_wt'] ?? 0);
    $alloy_wt = (float)($item['alloy_wt'] ?? 0);
    $final_weight = (float)($item['final_weight'] ?? 0);
    $net_weight = (float)($item['net_weight'] ?? 0);
    $pure_weight = (float)($item['pure_weight'] ?? 0);
    $rate = (float)($item['rate'] ?? 0);
    $making_amount = (float)($item['making_amount'] ?? 0);
    $amount = (float)($item['amount'] ?? 0);
    $tax_amount = (float)($item['tax'] ?? 0);
    $net_amount = (float)($item['net_amount'] ?? 0);
    $net_amt_with_tax = (float)($item['net_amt_with_tax'] ?? 0);

    $char_sql = $characteristic_id !== null ? $characteristic_id : 'NULL';
    $barcode_sql = $barcode !== '' ? "'$barcode'" : 'NULL';
    $design_sql = $design_no !== '' ? "'$design_no'" : 'NULL';
    $carat_sql = $carat !== '' ? "'$carat'" : 'NULL';

    $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_material_issue_items', $item);
    $ins_item = "INSERT INTO tbl_material_issue_items (material_issue_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, requested_purity, requested_wt, alloy_wt, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax{$ef_parts['columns']}, status, created_at) VALUES ($new_jwo_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $requested_purity, $requested_wt, $alloy_wt, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax{$ef_parts['values']}, 1, NOW())";
    mysqli_query($conn, $ins_item);
    $mi_line_id = (int) mysqli_insert_id($conn);
    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
    $mi_dt_new = $standalone_mi
        ? ($header_order_date !== '' ? substr($header_order_date, 0, 10) : date('Y-m-d'))
        : (!empty($sale_order['order_date']) ? substr(trim((string) $sale_order['order_date']), 0, 10) : date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mi_dt_new)) {
        $mi_dt_new = date('Y-m-d');
    }
    $issue_wt_new = auragold_material_issue_resolve_issue_weight(array_merge($item, [
        'final_weight' => $final_weight,
        'net_weight' => $net_weight,
        'gross_weight' => $gross_weight,
        'requested_wt' => $requested_wt,
    ]));
    $metal_id_line_new = (int) ($item['metal_id'] ?? 0);
    if ($metal_id_line_new <= 0 && $characteristic_id !== null && (int) $characteristic_id > 0) {
        $mr_lnn = getRecord('SELECT metal_id FROM tbl_product_characteristics WHERE id = ' . (int) $characteristic_id . ' AND status = 1 LIMIT 1');
        $metal_id_line_new = (int) ($mr_lnn['metal_id'] ?? 0);
    }
    auragold_material_issue_apply_barcode_stock_deduct($conn, (int) $new_jwo_id, $mi_dt_new, array_merge($item, [
        'product_id' => $product_id,
        'characteristic_id' => $characteristic_id,
        'metal_id' => $metal_id_line_new,
    ]), $issue_wt_new);
    $metal_base_n = auragold_material_issue_line_metal_wt(array_merge($item, [
        'final_weight' => $final_weight,
        'net_weight' => $net_weight,
        'gross_weight' => $gross_weight,
    ]));
    $audit_pw_n = $purity_weight;
    if ($issue_wt_new > 0.0000001 && $metal_base_n > 0.0000001 && abs($issue_wt_new - $metal_base_n) > 0.0000001 && $purity_weight > 0.0000001) {
        $audit_pw_n = round($purity_weight * ($issue_wt_new / $metal_base_n), 4);
    }
    auragold_stock_history_audit_for_document_barcode_line($conn, 'Material Issue', $material_issue_no, $mi_dt_new, 'MI', (int) $new_jwo_id, $mi_line_id, 'mi', array_merge($item, [
        'product_id' => $product_id,
        'product_characteristic_id' => $characteristic_id !== null ? (int) $characteristic_id : 0,
        'final_weight' => $issue_wt_new,
        'purity_weight' => $audit_pw_n,
        'gross_weight' => $issue_wt_new > 0.0000001 ? $issue_wt_new : ((float) ($item['gross_weight'] ?? 0)),
        'net_weight' => $issue_wt_new > 0.0000001 ? $issue_wt_new : ((float) ($item['net_weight'] ?? 0)),
    ]));
}

if (!$standalone_mi && $sale_order_id > 0) {
    auragold_sync_sale_order_department($conn, $sale_order_id, $department_id);
    auragold_sync_sale_order_sales_person($conn, $sale_order_id, $sales_person_post);
    mysqli_query($conn, "UPDATE tbl_sale_orders SET status = 'processing' WHERE id = $sale_order_id");
}

require_once __DIR__ . '/../includes/auragold_material_issue_rows_for_sale_order.php';
$me_mi_stock_payments_ins = function_exists('auragold_material_issue_payments_for_mi_stock_save')
    ? auragold_material_issue_payments_for_mi_stock_save($conn, $me_mi_payments)
    : $me_mi_payments;
if (!empty($me_mi_stock_payments_ins)) {
    try {
        foreach ($me_mi_stock_payments_ins as $__pmi_ins) {
            if (!is_array($__pmi_ins)) {
                continue;
            }
            $__mmi_ins = auragold_payment_merge_stored_details($__pmi_ins);
            if (!auragold_payment_is_metal_exchange_inward($conn, $__mmi_ins)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $__mmi_ins);
        }
        $rmi_ins_hdr = @getRecord('SELECT material_issue_no, order_date FROM tbl_material_issues WHERE id = ' . (int) $new_jwo_id . ' LIMIT 1');
        $__mi_no_ins = trim((string) ($rmi_ins_hdr['material_issue_no'] ?? $material_issue_no));
        $__mi_dt_ins = substr(trim((string) ($rmi_ins_hdr['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__mi_dt_ins)) {
            $__mi_dt_ins = $standalone_mi ? ($header_order_date !== '' ? substr($header_order_date, 0, 10) : date('Y-m-d')) : (!empty($sale_order['order_date']) ? substr(trim((string) $sale_order['order_date']), 0, 10) : date('Y-m-d'));
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__mi_dt_ins)) {
                $__mi_dt_ins = date('Y-m-d');
            }
        }
        $___mi_ins_me_ref = auragold_metal_exchange_document_init($conn, false, (int) $new_jwo_id, 'material_issue_metal_exchange');
        $pay_seq_ins = 0;
        $me_reserved_bc_ins = [];
        $me_reserved_stock_ids_ins = [];
        foreach ($me_mi_stock_payments_ins as $payment) {
            if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
                continue;
            }
            $___pm_mi_ins = auragold_payment_merge_stored_details($payment);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'material_issue_metal_exchange',
                (int) $new_jwo_id,
                $__mi_no_ins,
                $__mi_dt_ins,
                $___pm_mi_ins,
                auragold_metal_exchange_default_branch_id(),
                $pay_seq_ins,
                $___mi_ins_me_ref,
                'Material Issue — Metal Exchange',
                'mi_me',
                'MI-ME-',
                $metal_exchange_barcodes_out,
                $me_reserved_bc_ins,
                $me_reserved_stock_ids_ins
            );
            foreach ($metal_exchange_barcodes_out as $__me_bc_row_ins) {
                $__me_bc_ins = trim((string) ($__me_bc_row_ins['barcode'] ?? ''));
                if ($__me_bc_ins !== '' && !in_array($__me_bc_ins, $me_reserved_bc_ins, true)) {
                    $me_reserved_bc_ins[] = $__me_bc_ins;
                }
                $__me_sid_ins = (int) ($__me_bc_row_ins['stock_id'] ?? 0);
                if ($__me_sid_ins > 0 && !in_array($__me_sid_ins, $me_reserved_stock_ids_ins, true)) {
                    $me_reserved_stock_ids_ins[] = $__me_sid_ins;
                }
            }
            $pay_seq_ins++;
        }
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
$rmi_apply_ins = @getRecord('SELECT material_issue_no, order_date FROM tbl_material_issues WHERE id = ' . (int) $new_jwo_id . ' LIMIT 1');
if ($rmi_apply_ins && (int) $new_jwo_id > 0) {
    $od_ins = substr(trim((string) ($rmi_apply_ins['order_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od_ins)) {
        $od_ins = date('Y-m-d');
    }
    auragold_voucher_apply_pending_diamond_stone_from_post(
        $conn,
        'material_issue',
        (int) $new_jwo_id,
        trim((string) ($rmi_apply_ins['material_issue_no'] ?? '')),
        $od_ins
    );
}

require_once __DIR__ . '/../includes/auragold_notifications.php';
$rmi_new = @getRecord('SELECT material_issue_no, customer_name, order_date, due_date FROM tbl_material_issues WHERE id = ' . (int) $new_jwo_id . ' LIMIT 1');
if (is_array($rmi_new)) {
    $dd = isset($rmi_new['due_date']) && $rmi_new['due_date'] !== null && trim((string) $rmi_new['due_date']) !== ''
        ? substr(trim((string) $rmi_new['due_date']), 0, 10) : '';
    $od = substr(trim((string) ($rmi_new['order_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od)) {
        $od = date('Y-m-d');
    }
    auragold_notify_document_saved($conn, [
        'label' => 'Material Issue',
        'verb' => 'created',
        'number' => trim((string) ($rmi_new['material_issue_no'] ?? '')),
        'party' => trim((string) ($rmi_new['customer_name'] ?? '')),
        'doc_date' => $od,
        'due_date' => $dd,
        'ref_id' => (int) $new_jwo_id,
    ]);
}

echo json_encode([
    'status' => 'success',
    'message' => 'Material issue created',
    'jwo_id' => $new_jwo_id,
    'job_work_no' => $material_issue_no,
    'jobwork_no' => $material_issue_no,
    'material_issue_no' => $material_issue_no,
    'sale_order_id' => $standalone_mi ? 0 : $sale_order_id,
    'new_barcodes' => $metal_exchange_barcodes_out,
]);
