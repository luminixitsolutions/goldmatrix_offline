<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/../includes/auragold_extra_fields_item_values.php';

header('Content-Type: application/json; charset=utf-8');

if (function_exists('auragold_require_login_or_exit')) {
    auragold_require_login_or_exit();
}

@set_time_limit(120);

/** @return int */
function auragold_jwo_json_encode_flags(): int
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    return $flags;
}

function auragold_jwo_json_echo(array $payload, int $http_code = 200): void
{
    $json = json_encode($payload, auragold_jwo_json_encode_flags());
    if ($json === false) {
        $json = json_encode([
            'status' => 'error',
            'message' => 'Could not encode server response: ' . json_last_error_msg(),
        ], auragold_jwo_json_encode_flags());
    }
    if ($http_code !== 200) {
        http_response_code($http_code);
    }
    echo $json;
    exit;
}

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (headers_sent()) {
        return;
    }
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    auragold_jwo_json_echo([
        'status' => 'error',
        'message' => 'Server error: ' . ($err['message'] ?? 'unknown'),
    ], 500);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auragold_jwo_json_echo(['status' => 'error', 'message' => 'Invalid request'], 405);
}

$sale_order_id = isset($_POST['sale_order_id']) ? (int)$_POST['sale_order_id'] : 0;
/** When 1, always INSERT a new tbl_jobwork_orders row (do not reuse existing JWO for same sale order). Used for one-JWO-per-line from sale-order-process. */
$force_new_jwo = isset($_POST['force_new_jwo']) && ($_POST['force_new_jwo'] === '1' || $_POST['force_new_jwo'] === 1 || $_POST['force_new_jwo'] === true);
require_once __DIR__ . '/../includes/jwm_list_helpers.php';
$jwo_status = isset($_POST['jwo_status']) ? trim($_POST['jwo_status']) : 'Processing';
if (function_exists('auragold_jwo_status_canonical_value')) {
    $jwo_status = auragold_jwo_status_canonical_value($jwo_status);
}
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
$skip_metal_exchange_auto_issue = isset($_POST['skip_metal_exchange_auto_issue'])
    && ($_POST['skip_metal_exchange_auto_issue'] === '1' || $_POST['skip_metal_exchange_auto_issue'] === 1 || $_POST['skip_metal_exchange_auto_issue'] === true);

// Items JSON (product list from form)
$items_json = isset($_POST['items']) ? $_POST['items'] : '';
$items = [];
if (is_string($items_json) && $items_json !== '') {
    $items = json_decode($items_json, true);
}
if (!is_array($items)) {
    $items = [];
}

$jwo_payments_raw = isset($_POST['payments']) ? $_POST['payments'] : '';
$jwo_payments = [];
if (is_string($jwo_payments_raw) && $jwo_payments_raw !== '') {
    $jwo_payments = json_decode($jwo_payments_raw, true);
}
if (!is_array($jwo_payments)) {
    $jwo_payments = [];
}
if ($sale_order_id > 0 && $jwo_payments !== []) {
    $jwo_payments = array_values(array_filter($jwo_payments, function ($pay) {
        if (!is_array($pay)) {
            return false;
        }
        if (!empty($pay['readonly_from_sale_order'])) {
            return false;
        }

        return true;
    }));
}
$metal_exchange_barcodes_out = [];

if ($sale_order_id < 1) {
    auragold_jwo_json_echo(['status' => 'error', 'message' => 'Sale order ID required']);
}

// Use new tables: tbl_jobwork_orders, tbl_jobwork_order_items
$tbl_master = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
$tbl_items = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
if (!$tbl_master || mysqli_num_rows($tbl_master) === 0 || !$tbl_items || mysqli_num_rows($tbl_items) === 0) {
    if ($tbl_master) mysqli_free_result($tbl_master);
    if ($tbl_items) mysqli_free_result($tbl_items);
    auragold_jwo_json_echo(['status' => 'error', 'message' => 'Job work order tables not found. Please run admin/sql/create_tbl_jobwork_orders.sql']);
}
mysqli_free_result($tbl_master);
mysqli_free_result($tbl_items);

// Ensure optional columns exist (otherwise department/priority are never persisted)
$cd = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_id'");
if ($cd && mysqli_num_rows($cd) === 0) {
    mysqli_free_result($cd);
    @mysqli_query($conn, "ALTER TABLE `tbl_jobwork_orders` ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `customer_name`");
} elseif ($cd) {
    mysqli_free_result($cd);
}
$cp = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'priority'");
if ($cp && mysqli_num_rows($cp) === 0) {
    mysqli_free_result($cp);
    @mysqli_query($conn, "ALTER TABLE `tbl_jobwork_orders` ADD COLUMN `priority` varchar(30) DEFAULT 'Medium' AFTER `status`");
} elseif ($cp) {
    mysqli_free_result($cp);
}
$cdu = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_user_id'");
if ($cdu && mysqli_num_rows($cdu) === 0) {
    mysqli_free_result($cdu);
    @mysqli_query($conn, "ALTER TABLE `tbl_jobwork_orders` ADD COLUMN `department_user_id` int(11) DEFAULT NULL AFTER `department_id`");
} elseif ($cdu) {
    mysqli_free_result($cdu);
}

$cq_jqn = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'jobwork_queue_no'");
if ($cq_jqn && mysqli_num_rows($cq_jqn) === 0) {
    mysqli_free_result($cq_jqn);
    @mysqli_query($conn, "ALTER TABLE `tbl_jobwork_orders` ADD COLUMN `jobwork_queue_no` varchar(50) NOT NULL DEFAULT '' COMMENT 'Jobwork Queue No (bill series)' AFTER `jobwork_no`");
} elseif ($cq_jqn) {
    mysqli_free_result($cq_jqn);
}

$map_chk_req = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
$has_department_user_map = ($map_chk_req && mysqli_num_rows($map_chk_req) > 0);
if ($map_chk_req) {
    mysqli_free_result($map_chk_req);
}

// Department is required. When department–user map exists, Name (ledger in tbl_customers) is also required.
if ($department_id === null || (int)$department_id < 1) {
    auragold_jwo_json_echo(['status' => 'error', 'message' => 'Department is required']);
}
$department_id = (int)$department_id;
if ($has_department_user_map) {
    if (!$department_user_id_provided || $department_user_id === null || (int)$department_user_id < 1) {
        auragold_jwo_json_echo(['status' => 'error', 'message' => 'Name is required']);
    }
}

// If the browser did not send jwo_id (or it is stale 0) but a JWO row exists for this sale order, update that row — unless forcing a new JWO (one job work order per sale order line item).
if ($jwo_id < 1 && !$force_new_jwo) {
    $row_existing = getRecord("SELECT id FROM tbl_jobwork_orders WHERE sale_order_id = $sale_order_id LIMIT 1");
    if ($row_existing && !empty($row_existing['id'])) {
        $jwo_id = (int)$row_existing['id'];
    }
}

$sale_order = getRecord("SELECT id, order_no, customer_name, order_date, due_date, grand_total, status FROM tbl_sale_orders WHERE id = $sale_order_id");
if (!$sale_order) {
    auragold_jwo_json_echo(['status' => 'error', 'message' => 'Sale order not found']);
}

// Keep tbl_sale_orders.department_id in sync with JWO (users often save department only on job work screen)
$cd_so = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'department_id'");
if ($cd_so && mysqli_num_rows($cd_so) === 0) {
    mysqli_free_result($cd_so);
    @mysqli_query($conn, "ALTER TABLE `tbl_sale_orders` ADD COLUMN `department_id` int(11) DEFAULT NULL AFTER `customer_name`");
} elseif ($cd_so) {
    mysqli_free_result($cd_so);
}

/** @param mysqli $conn */
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

/**
 * When posted line weights are all zero, copy from tbl_sale_order_items so manufacturing-process / inward stock show weight on first save.
 *
 * @param mysqli $conn
 * @param int    $sale_order_id
 * @param array<string,mixed> $item
 */
function auragold_jobwork_merge_weights_from_sale_order_line($conn, $sale_order_id, array &$item) {
    $sid = (int) $sale_order_id;
    if ($sid < 1) {
        return;
    }
    $g = (float) ($item['gross_weight'] ?? 0);
    $f = (float) ($item['final_weight'] ?? 0);
    $n = (float) ($item['net_weight'] ?? 0);
    if (($g > 0.0000001) || ($f > 0.0000001) || ($n > 0.0000001)) {
        return;
    }
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_items'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        if ($chk) {
            mysqli_free_result($chk);
        }
        return;
    }
    mysqli_free_result($chk);

    $pid = (int) ($item['product_id'] ?? 0);
    $cid = isset($item['characteristic_id']) && $item['characteristic_id'] !== '' && $item['characteristic_id'] !== null
        ? (int) $item['characteristic_id'] : 0;
    $barcode = isset($item['barcode']) ? trim((string) $item['barcode']) : '';
    $barcode_esc = mysqli_real_escape_string($conn, $barcode);

    $row = null;
    if ($pid > 0 && $cid > 0 && function_exists('getRecord')) {
        $row = getRecord('SELECT * FROM tbl_sale_order_items WHERE order_id = ' . $sid . ' AND product_id = ' . $pid
            . ' AND product_characteristic_id = ' . $cid . ' ORDER BY id ASC LIMIT 1');
    }
    if (!$row && $pid > 0 && $barcode !== '' && function_exists('getRecord')) {
        $row = getRecord('SELECT * FROM tbl_sale_order_items WHERE order_id = ' . $sid . ' AND product_id = ' . $pid
            . " AND TRIM(IFNULL(barcode,'')) = '" . $barcode_esc . "' ORDER BY id ASC LIMIT 1");
    }
    if (!$row && $pid > 0 && function_exists('getRecord')) {
        $row = getRecord('SELECT * FROM tbl_sale_order_items WHERE order_id = ' . $sid . ' AND product_id = ' . $pid . ' ORDER BY id ASC LIMIT 1');
    }
    if (!$row || !is_array($row)) {
        return;
    }

    $sg = isset($row['gross_weight']) ? (float) $row['gross_weight'] : 0.0;
    $sm = isset($row['metal_weight']) ? (float) $row['metal_weight'] : 0.0;
    if ($sg > 0.0000001) {
        $item['gross_weight'] = $sg;
    } elseif ($sm > 0.0000001) {
        $item['gross_weight'] = $sm;
    }

    if (isset($row['less_weight']) && (float) $row['less_weight'] > 0.0000001) {
        $item['less_weight'] = (float) $row['less_weight'];
    }
    if (isset($row['net_weight']) && (float) $row['net_weight'] > 0.0000001) {
        $item['net_weight'] = (float) $row['net_weight'];
    }
    if (isset($row['final_weight']) && (float) $row['final_weight'] > 0.0000001) {
        $item['final_weight'] = (float) $row['final_weight'];
    }
    $pp = null;
    if (isset($row['pure_weight'])) {
        $pp = (float) $row['pure_weight'];
    } elseif (isset($row['purity_weight'])) {
        $pp = (float) $row['purity_weight'];
    }
    if ($pp !== null && $pp > 0.0000001) {
        $item['pure_weight'] = $pp;
        $item['purity_weight'] = $pp;
    }
    if (isset($row['purity']) && (float) $row['purity'] > 0.0000001) {
        $item['purity'] = (float) $row['purity'];
    }
}

/** Mirror sales person from JWO form to tbl_sale_orders (same field as sale invoice). */
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

/**
 * Log assignment so manufacturing-process.php Inward / Outward stock grids show the job
 * (same tbl_jobwork_queue_activity row as ajax/mp-save-jobwork-queue.php after To Dept / To User).
 */
function auragold_jwo_log_manufacturing_queue_activity($conn, $jobwork_order_id, $to_dept_id, $to_user_id) {
    $jid = (int)$jobwork_order_id;
    $td = (int)$to_dept_id;
    if ($jid < 1 || $td < 1) {
        return;
    }
    $tu_sql = 'NULL';
    if ($to_user_id !== null && (int)$to_user_id > 0) {
        $tu_sql = (string)(int)$to_user_id;
    }
    $act_chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
    if (!$act_chk || mysqli_num_rows($act_chk) === 0) {
        if ($act_chk) {
            mysqli_free_result($act_chk);
        }
        @mysqli_query($conn, 'CREATE TABLE IF NOT EXISTS `tbl_jobwork_queue_activity` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `jobwork_order_id` int(11) NOT NULL,
      `jobwork_queue_no` varchar(50) NOT NULL DEFAULT \'\',
      `from_dept_id` int(11) DEFAULT NULL,
      `from_user_id` int(11) DEFAULT NULL,
      `to_dept_id` int(11) DEFAULT NULL,
      `to_user_id` int(11) DEFAULT NULL,
      `activity_action` varchar(32) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `jobwork_order_id` (`jobwork_order_id`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } elseif ($act_chk) {
        mysqli_free_result($act_chk);
    }
    $aca_so = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'activity_action'");
    if (!$aca_so || mysqli_num_rows($aca_so) === 0) {
        @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_queue_activity ADD COLUMN activity_action varchar(32) DEFAULT NULL AFTER to_user_id');
    }
    if ($aca_so) {
        mysqli_free_result($aca_so);
    }
    $acf_so = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'from_dept_id'");
    if (!$acf_so || mysqli_num_rows($acf_so) === 0) {
        @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_queue_activity ADD COLUMN from_dept_id int(11) DEFAULT NULL AFTER jobwork_queue_no, ADD COLUMN from_user_id int(11) DEFAULT NULL AFTER from_dept_id');
    }
    if ($acf_so) {
        mysqli_free_result($acf_so);
    }
    $queue_no = '';
    if (function_exists('ensureJobworkQueueNoForOrder')) {
        $qn = ensureJobworkQueueNoForOrder($conn, $jid);
        if ($qn !== null && $qn !== '') {
            $queue_no = trim((string)$qn);
        }
    }
    if ($queue_no === '' && function_exists('getRecord')) {
        $jrow = getRecord("SELECT jobwork_queue_no FROM tbl_jobwork_orders WHERE id = $jid LIMIT 1");
        if ($jrow && isset($jrow['jobwork_queue_no'])) {
            $queue_no = trim((string)$jrow['jobwork_queue_no']);
        }
    }
    $qn_esc = mysqli_real_escape_string($conn, $queue_no);
    @mysqli_query($conn, 'INSERT INTO tbl_jobwork_queue_activity (jobwork_order_id, jobwork_queue_no, from_dept_id, from_user_id, to_dept_id, to_user_id, activity_action) VALUES ('
        . $jid . ', \'' . $qn_esc . '\', NULL, NULL, ' . $td . ', ' . $tu_sql . ", 'jobwork_create')");
}

/** Emit JSON error and stop (keeps AJAX response parseable when validation throws). */
function auragold_jwo_json_error_and_exit(string $message): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    auragold_jwo_json_echo(['status' => 'error', 'message' => $message]);
}

/** Optional POS-style payments JSON: persists tagged rows on tbl_sale_order_payments + posts ME stock keyed by jobwork order id. */
function auragold_jobwork_order_persist_sale_order_payments_and_post_me(mysqli $conn, int $sale_order_id, int $jwo_id, bool $jwo_is_update, string $jobwork_no_plain, string $doc_date_ymd, array $payments, array &$metal_exchange_barcodes_out): void
{
    if ($sale_order_id < 1 || $jwo_id < 1 || empty($payments)) {
        return;
    }

    foreach ($payments as $__jp) {
        if (!is_array($__jp)) {
            continue;
        }
        $__mjp = auragold_payment_merge_stored_details($__jp);
        if (!auragold_payment_is_metal_exchange_inward($conn, $__mjp)) {
            continue;
        }
        if (function_exists('auragold_jwo_metal_exchange_strip_receive_source_ids')) {
            $__mjp = auragold_jwo_metal_exchange_strip_receive_source_ids($__mjp);
        }
        $__src_stock = (int) ($__mjp['metal_exchange_source_stock_id'] ?? $__mjp['source_issue_stock_id'] ?? 0);
        if ($__src_stock > 0) {
            // Issue from sale-order ME stock is handled by auto_issue on JWO save.
            continue;
        }
        if (!function_exists('auragold_jobwork_order_payment_issues_shop_metal')
            || !auragold_jobwork_order_payment_issues_shop_metal($conn, $__mjp)) {
            continue;
        }
        $__inv_stock = function_exists('auragold_resolve_inventory_stock_for_metal_exchange')
            ? auragold_resolve_inventory_stock_for_metal_exchange($conn, $__mjp)
            : null;
        if (is_array($__inv_stock)) {
            $__wt_gross = (float) auragold_metal_exchange_resolve($conn, $__mjp)['gross'];
            $__avail = function_exists('auragold_inventory_stock_available_weight')
                ? auragold_inventory_stock_available_weight($__inv_stock)
                : 0.0;
            if ($__wt_gross > $__avail + 0.0001) {
                auragold_jwo_json_error_and_exit(
                    'Metal exchange issue weight (' . round($__wt_gross, 3) . ') exceeds available stock ('
                    . round(max(0, $__avail), 3) . ').'
                );
            }
            try {
                auragold_validate_metal_exchange_for_stock($conn, $__mjp);
            } catch (Throwable $e) {
                auragold_jwo_json_error_and_exit($e->getMessage());
            }
            continue;
        }
        try {
            auragold_validate_metal_exchange_for_stock($conn, $__mjp);
        } catch (Throwable $e) {
            auragold_jwo_json_error_and_exit($e->getMessage());
        }
        auragold_jwo_json_error_and_exit(
            'Metal exchange: no shop stock found for the selected metal/product. Add opening stock or enter a valid item code.'
        );
    }

    if ($jwo_is_update && function_exists('auragold_jobwork_order_reverse_metal_outward_stock')) {
        auragold_jobwork_order_reverse_metal_outward_stock($conn, $jwo_id);
    }

    $___jwo_me_ref = auragold_metal_exchange_document_init($conn, $jwo_is_update, $jwo_id, 'jobwork_order_metal_exchange');

    if ($jwo_is_update) {
        $__res_pd = mysqli_query($conn, 'SELECT id, payment_details FROM tbl_sale_order_payments WHERE order_id=' . (int) $sale_order_id);
        if ($__res_pd) {
            while ($__rw = mysqli_fetch_assoc($__res_pd)) {
                $__jd = isset($__rw['payment_details']) ? json_decode((string) $__rw['payment_details'], true) : null;
                if (is_array($__jd) && !empty($__jd['jobwork_order_metal_exchange']) && (int) ($__jd['jobwork_order_id'] ?? 0) === $jwo_id) {
                    mysqli_query($conn, 'DELETE FROM tbl_sale_order_payments WHERE id=' . (int) $__rw['id']);
                }
            }
            mysqli_free_result($__res_pd);
        }
    }

    $sop_pd_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_payments LIKE 'payment_details'");
    $sop_has_payment_details = ($sop_pd_chk && mysqli_num_rows($sop_pd_chk) > 0);
    if ($sop_pd_chk) {
        mysqli_free_result($sop_pd_chk);
    }
    if (!$sop_has_payment_details) {
        @mysqli_query($conn, "ALTER TABLE tbl_sale_order_payments ADD COLUMN payment_details TEXT NULL COMMENT 'JSON'");
        $__sopc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_payments LIKE 'payment_details'");
        $sop_has_payment_details = ($__sopc && mysqli_num_rows($__sopc) > 0);
        if ($__sopc) {
            mysqli_free_result($__sopc);
        }
    }
    $__spc_pb = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_payments LIKE 'previous_balance_amount'");
    $sop_has_prev_bal = ($__spc_pb && mysqli_num_rows($__spc_pb) > 0);
    if ($__spc_pb) {
        mysqli_free_result($__spc_pb);
    }

    foreach ($payments as $pay_seq => $payment) {
        if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $payment)) {
            continue;
        }

        $payment_merged = auragold_payment_merge_stored_details($payment);
        if (function_exists('auragold_jwo_metal_exchange_strip_receive_source_ids')
            && auragold_payment_is_metal_exchange_inward($conn, $payment_merged)) {
            $payment_merged = auragold_jwo_metal_exchange_strip_receive_source_ids($payment_merged);
        }
        $payment_type_esc = mysqli_real_escape_string($conn, (string) ($payment_merged['payment_type'] ?? ''));
        $deposit_into_esc = mysqli_real_escape_string($conn, (string) ($payment_merged['deposit_into'] ?? ''));
        $transaction_no_esc = mysqli_real_escape_string($conn, (string) ($payment_merged['transaction_no'] ?? ''));
        $cheque_date_esc = isset($payment_merged['cheque_date']) && $payment_merged['cheque_date']
            ? mysqli_real_escape_string($conn, (string) $payment_merged['cheque_date']) : '';
        $purity_carat_esc = mysqli_real_escape_string($conn, (string) ($payment_merged['purity_carat'] ?? ''));
        $amount = (float) ($payment_merged['amount'] ?? 0);
        if ($amount < 0.00001 && function_exists('auragold_metal_exchange_payment_display_amount')) {
            $amount = auragold_metal_exchange_payment_display_amount($payment_merged);
        }
        $previous_balance_amount = (float) ($payment_merged['previous_balance_amount'] ?? 0);
        $diamond_category_esc = mysqli_real_escape_string($conn, (string) ($payment_merged['diamond_category'] ?? ''));
        $quantity = (float) ($payment_merged['quantity'] ?? 0);

        $pd_wrap = array_merge(['jobwork_order_metal_exchange' => 1, 'jobwork_order_id' => $jwo_id], $payment_merged);
        $__inv_src = null;
        $__issues_shop_metal = function_exists('auragold_jobwork_order_payment_issues_shop_metal')
            && auragold_jobwork_order_payment_issues_shop_metal($conn, $payment_merged);
        if ($__issues_shop_metal) {
            $__inv_src = function_exists('auragold_resolve_inventory_stock_for_metal_exchange')
                ? auragold_resolve_inventory_stock_for_metal_exchange($conn, $payment_merged)
                : null;
            if (!is_array($__inv_src)) {
                auragold_jwo_json_error_and_exit(
                    'Metal exchange: no shop stock found for the selected metal/product. Add opening stock or enter a valid item code.'
                );
            }
            $pd_wrap['jobwork_order_metal_outward'] = 1;
            $pd_wrap['inventory_source_stock_id'] = (int) ($__inv_src['id'] ?? 0);
        }
        unset($pd_wrap['id'], $pd_wrap['payment_details']);
        $pd_sql_part = 'NULL';
        if ($sop_has_payment_details) {
            $pd_json = json_encode($pd_wrap, auragold_jwo_json_encode_flags());
            if ($pd_json === false) {
                auragold_jwo_json_error_and_exit('Could not encode payment details: ' . json_last_error_msg());
            }
            $pd_sql_part = "'" . mysqli_real_escape_string($conn, $pd_json) . "'";
        }

        $dep_sql = $deposit_into_esc !== '' ? "'$deposit_into_esc'" : 'NULL';
        $txn_sql = $transaction_no_esc !== '' ? "'$transaction_no_esc'" : 'NULL';
        $ch_sql = $cheque_date_esc !== '' ? "'$cheque_date_esc'" : 'NULL';
        $pur_sql = $purity_carat_esc !== '' ? "'$purity_carat_esc'" : 'NULL';
        $dia_sql = $diamond_category_esc !== '' ? "'$diamond_category_esc'" : 'NULL';

        $payment_sql = '';
        if ($sop_has_payment_details && $sop_has_prev_bal) {
            $payment_sql = "INSERT INTO tbl_sale_order_payments (order_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, previous_balance_amount, diamond_category, quantity, payment_details, status, created_at) VALUES ("
                . (int) $sale_order_id . ", '$payment_type_esc', $dep_sql, $txn_sql, $ch_sql, $pur_sql, $amount, $previous_balance_amount, $dia_sql, $quantity, $pd_sql_part, 1, NOW())";
        } elseif ($sop_has_payment_details) {
            $payment_sql = "INSERT INTO tbl_sale_order_payments (order_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, diamond_category, quantity, payment_details, status, created_at) VALUES ("
                . (int) $sale_order_id . ", '$payment_type_esc', $dep_sql, $txn_sql, $ch_sql, $pur_sql, $amount, $dia_sql, $quantity, $pd_sql_part, 1, NOW())";
        } elseif ($sop_has_prev_bal) {
            $payment_sql = "INSERT INTO tbl_sale_order_payments (order_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, previous_balance_amount, diamond_category, quantity, status, created_at) VALUES ("
                . (int) $sale_order_id . ", '$payment_type_esc', $dep_sql, $txn_sql, $ch_sql, $pur_sql, $amount, $previous_balance_amount, $dia_sql, $quantity, 1, NOW())";
        } else {
            $payment_sql = "INSERT INTO tbl_sale_order_payments (order_id, payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, diamond_category, quantity, status, created_at) VALUES ("
                . (int) $sale_order_id . ", '$payment_type_esc', $dep_sql, $txn_sql, $ch_sql, $pur_sql, $amount, $dia_sql, $quantity, 1, NOW())";
        }

        if (!mysqli_query($conn, $payment_sql)) {
            auragold_jwo_json_echo(['status' => 'error', 'message' => 'Jobwork order payment save failed: ' . mysqli_error($conn)]);
        }

        $pm_saved = auragold_payment_merge_stored_details($payment_merged);
        try {
            if ($__issues_shop_metal && is_array($__inv_src) && function_exists('auragold_post_jobwork_order_metal_outward_from_inventory')) {
                auragold_post_jobwork_order_metal_outward_from_inventory(
                    $conn,
                    (int) $jwo_id,
                    $jobwork_no_plain,
                    substr(trim((string) $doc_date_ymd), 0, 10),
                    $pm_saved,
                    is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                    $__inv_src
                );
            }
        } catch (Throwable $e) {
            auragold_jwo_json_error_and_exit($e->getMessage());
        }
    }
}

$sale_order_no = mysqli_real_escape_string($conn, $sale_order['order_no']);
$customer_name = mysqli_real_escape_string($conn, $sale_order['customer_name'] ?? '');
$order_date = !empty($sale_order['order_date']) ? mysqli_real_escape_string($conn, $sale_order['order_date']) : 'NULL';
$due_date = !empty($sale_order['due_date']) ? "'" . mysqli_real_escape_string($conn, $sale_order['due_date']) . "'" : 'NULL';
$grand_total = (float)($sale_order['grand_total'] ?? 0);

if ($jwo_id > 0) {
    // Update existing JWO (edit mode): update master and replace items
    $jwo = getRecord("SELECT id, sale_order_id, status, department_id, department_user_id FROM tbl_jobwork_orders WHERE id = $jwo_id");
    if (!$jwo) {
        auragold_jwo_json_echo(['status' => 'error', 'message' => 'Job work order not found']);
    }
    if ((int)$jwo['sale_order_id'] !== $sale_order_id) {
        auragold_jwo_json_echo(['status' => 'error', 'message' => 'Sale order mismatch']);
    }
    $new_status = mysqli_real_escape_string($conn, $jwo_status);
    $grand_total = 0;
    foreach ($items as $item) {
        $grand_total += (float)($item['net_amt_with_tax'] ?? $item['net_amount'] ?? 0);
    }
    $grand_total = round($grand_total, 2);
    $cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_id'");
    $has_dept = ($cols && mysqli_num_rows($cols) > 0);
    if ($cols) mysqli_free_result($cols);
    $cols2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'priority'");
    $has_priority = ($cols2 && mysqli_num_rows($cols2) > 0);
    if ($cols2) mysqli_free_result($cols2);
    $cols_du = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_user_id'");
    $has_dept_user = ($cols_du && mysqli_num_rows($cols_du) > 0);
    if ($cols_du) {
        mysqli_free_result($cols_du);
    }
    $upd = "UPDATE tbl_jobwork_orders SET status = '$new_status', grand_total = $grand_total, updated_at = NOW()";
    // Only update department_id when a valid option was selected (not --Select--)
    if ($has_dept && $department_id !== null && $department_id > 0) {
        $upd .= ", department_id = " . (int)$department_id;
    }
    if ($has_priority) {
        $priority_esc = mysqli_real_escape_string($conn, $priority);
        $upd .= ", priority = '$priority_esc'";
    }
    if ($has_dept_user && $department_user_id_provided) {
        if ($department_user_id !== null && $department_user_id > 0) {
            $upd .= ", department_user_id = " . (int)$department_user_id;
        } else {
            $upd .= ", department_user_id = NULL";
        }
    }
    $upd .= " WHERE id = $jwo_id";
    mysqli_query($conn, $upd);
    mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=jwo|hid=" . (int) $jwo_id . "|%' OR comment LIKE 'auragold_doc|src=jwo_out|rid=" . (int) $jwo_id . "|%'");
    mysqli_query($conn, "DELETE FROM tbl_jobwork_order_items WHERE jobwork_order_id = $jwo_id");
    foreach ($items as $ik => $_tmp) {
        auragold_jobwork_merge_weights_from_sale_order_line($conn, $sale_order_id, $items[$ik]);
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
        $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_jobwork_order_items', $item);
        $ins_item = "INSERT INTO tbl_jobwork_order_items (jobwork_order_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax{$ef_parts['columns']}, status, created_at) VALUES ($jwo_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax{$ef_parts['values']}, 1, NOW())";
        mysqli_query($conn, $ins_item);
        $jwo_line_id = (int) mysqli_insert_id($conn);
        require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
        $jwb = getRecord("SELECT jobwork_no, order_date FROM tbl_jobwork_orders WHERE id = " . (int) $jwo_id . " LIMIT 1");
        $jw_no = trim((string) ($jwb['jobwork_no'] ?? ''));
        $jw_dt = substr(trim((string) ($jwb['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $jw_dt) && !empty($sale_order['order_date'])) {
            $jw_dt = substr(trim((string) $sale_order['order_date']), 0, 10);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $jw_dt)) {
            $jw_dt = date('Y-m-d');
        }
        auragold_stock_history_audit_for_document_barcode_line($conn, 'Jobwork Order', $jw_no, $jw_dt, 'JWO', (int) $jwo_id, $jwo_line_id, 'jwo', array_merge($item, [
            'product_id' => $product_id,
            'product_characteristic_id' => $characteristic_id !== null ? (int) $characteristic_id : 0,
        ]));
    }
    if (strtolower($new_status) === 'completed') {
        mysqli_query($conn, "UPDATE tbl_sale_orders SET status = 'completed', updated_at = NOW() WHERE id = $sale_order_id");
    }
    auragold_sync_sale_order_department($conn, $sale_order_id, $department_id);
    auragold_sync_sale_order_sales_person($conn, $sale_order_id, $sales_person_post);
    $new_dept = ($department_id !== null && (int)$department_id > 0) ? (int)$department_id : 0;
    $new_user = ($department_user_id !== null && (int)$department_user_id > 0) ? (int)$department_user_id : 0;
    $old_dept = (int)($jwo['department_id'] ?? 0);
    $old_user = isset($jwo['department_user_id']) && $jwo['department_user_id'] !== null && $jwo['department_user_id'] !== ''
        ? (int)$jwo['department_user_id'] : 0;
    $log_activity = false;
    if ($new_dept > 0) {
        if ($new_dept !== $old_dept || $new_user !== $old_user) {
            $log_activity = true;
        } else {
            $achk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
            if ($achk && mysqli_num_rows($achk) > 0) {
                mysqli_free_result($achk);
                $ex = @mysqli_query($conn, 'SELECT id FROM tbl_jobwork_queue_activity WHERE jobwork_order_id = ' . (int)$jwo_id . ' LIMIT 1');
                if ($ex && mysqli_num_rows($ex) === 0) {
                    $log_activity = true;
                }
                if ($ex) {
                    mysqli_free_result($ex);
                }
            } elseif ($achk) {
                mysqli_free_result($achk);
            }
        }
    }
    // Assigned to dept + user on JWO: no manufacturing queue inward row (transfers only on manufacturing-process).
    if ($log_activity && !($new_dept > 0 && $new_user > 0)) {
        auragold_jwo_log_manufacturing_queue_activity($conn, $jwo_id, $new_dept, $department_user_id);
    }
    require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
    $rjw_apply_upd = @getRecord('SELECT jobwork_no, order_date FROM tbl_jobwork_orders WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
    if ($rjw_apply_upd && (int) $jwo_id > 0) {
        $od_jwu = substr(trim((string) ($rjw_apply_upd['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od_jwu)) {
            $od_jwu = date('Y-m-d');
        }
        try {
            auragold_voucher_apply_pending_diamond_stone_from_post(
                $conn,
                'jobwork_order',
                (int) $jwo_id,
                trim((string) ($rjw_apply_upd['jobwork_no'] ?? '')),
                $od_jwu
            );
        } catch (Throwable $e) {
            auragold_jwo_json_error_and_exit($e->getMessage());
        }
    }
    if (!empty($jwo_payments) && isset($rjw_apply_upd) && is_array($rjw_apply_upd)) {
        $__jwo_pay_dt = substr(trim((string) ($rjw_apply_upd['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__jwo_pay_dt)) {
            $__jwo_pay_dt = date('Y-m-d');
        }
        auragold_jobwork_order_persist_sale_order_payments_and_post_me(
            $conn,
            (int) $sale_order_id,
            (int) $jwo_id,
            true,
            trim((string) ($rjw_apply_upd['jobwork_no'] ?? '')),
            $__jwo_pay_dt,
            $jwo_payments,
            $metal_exchange_barcodes_out
        );
    }
    require_once __DIR__ . '/../includes/auragold_jobwork_order_metal_exchange_issue.php';
    if (!$skip_metal_exchange_auto_issue) {
        try {
            auragold_jobwork_order_auto_issue_sale_order_metal_exchange(
                $conn,
                (int) $sale_order_id,
                (int) $jwo_id,
                $department_id,
                $department_user_id,
                $priority,
                $jwo_status,
                $metal_exchange_barcodes_out
            );
            auragold_jobwork_order_auto_issue_jwo_metal_exchange_stocks(
                $conn,
                (int) $sale_order_id,
                (int) $jwo_id,
                $department_id,
                $department_user_id,
                $priority,
                $jwo_status,
                $metal_exchange_barcodes_out
            );
        } catch (Throwable $e) {
            auragold_jwo_json_error_and_exit($e->getMessage());
        }
    }
    try {
        require_once __DIR__ . '/../includes/auragold_jobwork_order_customer_ledger.php';
        auragold_jobwork_order_sync_customer_ledger($conn, (int) $sale_order_id, (int) $jwo_id);
    } catch (Throwable $e) {
        error_log('auragold_jobwork_order_sync_customer_ledger (update): ' . $e->getMessage());
    }
    try {
        require_once __DIR__ . '/../includes/auragold_notifications.php';
        $rjw = @getRecord('SELECT jobwork_no, customer_name, order_date, due_date FROM tbl_jobwork_orders WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
        if (is_array($rjw)) {
            $dued = isset($rjw['due_date']) && $rjw['due_date'] !== null && trim((string) $rjw['due_date']) !== ''
                ? substr(trim((string) $rjw['due_date']), 0, 10) : '';
            $od = substr(trim((string) ($rjw['order_date'] ?? '')), 0, 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od)) {
                $od = date('Y-m-d');
            }
            auragold_notify_document_saved($conn, [
                'label' => 'Jobwork Order',
                'verb' => 'updated',
                'number' => trim((string) ($rjw['jobwork_no'] ?? '')),
                'party' => trim((string) ($rjw['customer_name'] ?? '')),
                'doc_date' => $od,
                'due_date' => $dued,
                'ref_id' => (int) $jwo_id,
            ]);
        }
    } catch (Throwable $e) {
        error_log('auragold_notify_document_saved (jwo update): ' . $e->getMessage());
    }
    auragold_jwo_json_echo([
        'status' => 'success',
        'message' => 'Job work order updated',
        'jwo_id' => $jwo_id,
        'jwo_status' => $jwo_status,
        'new_barcodes' => $metal_exchange_barcodes_out,
    ]);
}

// New JWO with exactly one line in this request: store that line's total on the master (not the whole sale order).
if (count($items) === 1) {
    $grand_total = 0;
    foreach ($items as $item) {
        $grand_total += (float)($item['net_amt_with_tax'] ?? $item['net_amount'] ?? 0);
    }
    $grand_total = round($grand_total, 2);
}

// Insert master — jobwork_no from Bill Series (bill-series.php, voucher type Jobwork Order / Job Work Order), else legacy JWO-1
$cfg_jwo = function_exists('getJobworkOrderBillSeriesConfig')
    ? getJobworkOrderBillSeriesConfig($conn)
    : ['prefix' => 'JWO-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
$jobwork_no = function_exists('getNextJobworkOrderNo') ? getNextJobworkOrderNo($conn) : 'JWO-1';
$jobwork_no_esc = mysqli_real_escape_string($conn, $jobwork_no);
$jwo_active = auragold_doc_series_active_sql($conn, 'tbl_jobwork_orders');
$existing_no = getRecord("SELECT id FROM tbl_jobwork_orders WHERE jobwork_no = '$jobwork_no_esc'" . $jwo_active);
$guard_no = 0;
while ($existing_no && $guard_no < 5000) {
    $jobwork_no = function_exists('bumpJobworkOrderNo') ? bumpJobworkOrderNo($conn, $jobwork_no, $cfg_jwo) : ($jobwork_no . '-1');
    $jobwork_no_esc = mysqli_real_escape_string($conn, $jobwork_no);
    $existing_no = getRecord("SELECT id FROM tbl_jobwork_orders WHERE jobwork_no = '$jobwork_no_esc'" . $jwo_active);
    $guard_no++;
}
if (function_exists('auragold_release_soft_deleted_document_no')) {
    auragold_release_soft_deleted_document_no($conn, [['tbl_jobwork_orders', 'jobwork_no']], $jobwork_no);
}

$status_esc = mysqli_real_escape_string($conn, $jwo_status);
$ins_master = "INSERT INTO tbl_jobwork_orders (jobwork_no, sale_order_id, sale_order_no, customer_name, order_date, due_date, grand_total, status, created_at) VALUES ('$jobwork_no_esc', $sale_order_id, '$sale_order_no', '$customer_name', " . ($order_date !== 'NULL' ? "'$order_date'" : 'NULL') . ", $due_date, $grand_total, '$status_esc', NOW())";
if (!mysqli_query($conn, $ins_master)) {
    auragold_jwo_json_echo(['status' => 'error', 'message' => 'Failed to create job work order: ' . mysqli_error($conn)]);
}

$new_jwo_id = mysqli_insert_id($conn);

// Save department_id and priority if columns exist (run sql/alter_tbl_jobwork_orders_add_columns.sql)
$cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_id'");
$has_dept = ($cols && mysqli_num_rows($cols) > 0);
if ($cols) mysqli_free_result($cols);
$cols2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'priority'");
$has_priority = ($cols2 && mysqli_num_rows($cols2) > 0);
if ($cols2) mysqli_free_result($cols2);
$cols_du = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_user_id'");
$has_dept_user = ($cols_du && mysqli_num_rows($cols_du) > 0);
if ($cols_du) {
    mysqli_free_result($cols_du);
}
if ($has_dept && $department_id !== null && $department_id > 0) {
    mysqli_query($conn, "UPDATE tbl_jobwork_orders SET department_id = " . (int)$department_id . " WHERE id = $new_jwo_id");
}
if ($has_priority) {
    $priority_esc = mysqli_real_escape_string($conn, $priority);
    mysqli_query($conn, "UPDATE tbl_jobwork_orders SET priority = '$priority_esc' WHERE id = $new_jwo_id");
}
if ($has_dept_user && $department_user_id_provided) {
    if ($department_user_id !== null && $department_user_id > 0) {
        mysqli_query($conn, "UPDATE tbl_jobwork_orders SET department_user_id = " . (int)$department_user_id . " WHERE id = $new_jwo_id");
    } else {
        mysqli_query($conn, "UPDATE tbl_jobwork_orders SET department_user_id = NULL WHERE id = $new_jwo_id");
    }
}

// Insert items
foreach ($items as $ik => $_tmp) {
    auragold_jobwork_merge_weights_from_sale_order_line($conn, $sale_order_id, $items[$ik]);
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

    $ef_parts = auragold_extra_fields_item_insert_sql_parts($conn, 'tbl_jobwork_order_items', $item);
    $ins_item = "INSERT INTO tbl_jobwork_order_items (jobwork_order_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax{$ef_parts['columns']}, status, created_at) VALUES ($new_jwo_id, $product_id, $char_sql, $barcode_sql, '$product_name', $design_sql, $carat_sql, $quantity, $gross_weight, $less_weight, $purity, $purity_weight, $final_weight, $net_weight, $pure_weight, $rate, $making_amount, $amount, $tax_amount, $net_amount, $net_amt_with_tax{$ef_parts['values']}, 1, NOW())";
    mysqli_query($conn, $ins_item);
    $jwo_line_id = (int) mysqli_insert_id($conn);
    require_once dirname(__DIR__) . '/includes/stock_history_audit_journal.php';
    $jw_dt_n = !empty($sale_order['order_date']) ? substr(trim((string) $sale_order['order_date']), 0, 10) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $jw_dt_n)) {
        $jw_dt_n = date('Y-m-d');
    }
    auragold_stock_history_audit_for_document_barcode_line($conn, 'Jobwork Order', $jobwork_no, $jw_dt_n, 'JWO', (int) $new_jwo_id, $jwo_line_id, 'jwo', array_merge($item, [
        'product_id' => $product_id,
        'product_characteristic_id' => $characteristic_id !== null ? (int) $characteristic_id : 0,
    ]));
}

// Update sale order status to processing (so sale-order-process.php shows "Processing") and mirror department / sales person
auragold_sync_sale_order_department($conn, $sale_order_id, $department_id);
auragold_sync_sale_order_sales_person($conn, $sale_order_id, $sales_person_post);
mysqli_query($conn, "UPDATE tbl_sale_orders SET status = 'processing' WHERE id = $sale_order_id");

$new_jwo_dept = ($department_id !== null && (int)$department_id > 0) ? (int)$department_id : 0;
$new_jwo_user = ($department_user_id !== null && (int)$department_user_id > 0) ? (int)$department_user_id : 0;
if ($new_jwo_dept > 0 && $new_jwo_user < 1) {
    auragold_jwo_log_manufacturing_queue_activity($conn, $new_jwo_id, $new_jwo_dept, $department_user_id);
}

require_once __DIR__ . '/../includes/auragold_voucher_pending_diamond_stone.php';
$rjw_apply_ins = @getRecord('SELECT jobwork_no, order_date FROM tbl_jobwork_orders WHERE id = ' . (int) $new_jwo_id . ' LIMIT 1');
if ($rjw_apply_ins && (int) $new_jwo_id > 0) {
    $od_jwi = substr(trim((string) ($rjw_apply_ins['order_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od_jwi)) {
        $od_jwi = date('Y-m-d');
    }
    try {
        auragold_voucher_apply_pending_diamond_stone_from_post(
            $conn,
            'jobwork_order',
            (int) $new_jwo_id,
            trim((string) ($rjw_apply_ins['jobwork_no'] ?? '')),
            $od_jwi
        );
    } catch (Throwable $e) {
        auragold_jwo_json_error_and_exit($e->getMessage());
    }
}

if (!empty($jwo_payments) && isset($rjw_apply_ins) && is_array($rjw_apply_ins)) {
    $__jwo_ins_pay_dt = substr(trim((string) ($rjw_apply_ins['order_date'] ?? '')), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $__jwo_ins_pay_dt)) {
        $__jwo_ins_pay_dt = date('Y-m-d');
    }
    auragold_jobwork_order_persist_sale_order_payments_and_post_me(
        $conn,
        (int) $sale_order_id,
        (int) $new_jwo_id,
        false,
        trim((string) ($rjw_apply_ins['jobwork_no'] ?? $jobwork_no)),
        $__jwo_ins_pay_dt,
        $jwo_payments,
        $metal_exchange_barcodes_out
    );
}

require_once __DIR__ . '/../includes/auragold_jobwork_order_metal_exchange_issue.php';
if (!$skip_metal_exchange_auto_issue) {
    try {
        auragold_jobwork_order_auto_issue_sale_order_metal_exchange(
            $conn,
            (int) $sale_order_id,
            (int) $new_jwo_id,
            $department_id,
            $department_user_id,
            $priority,
            $jwo_status,
            $metal_exchange_barcodes_out
        );
        auragold_jobwork_order_auto_issue_jwo_metal_exchange_stocks(
            $conn,
            (int) $sale_order_id,
            (int) $new_jwo_id,
            $department_id,
            $department_user_id,
            $priority,
            $jwo_status,
            $metal_exchange_barcodes_out
        );
    } catch (Throwable $e) {
        auragold_jwo_json_error_and_exit($e->getMessage());
    }
}

try {
    require_once __DIR__ . '/../includes/auragold_jobwork_order_customer_ledger.php';
    auragold_jobwork_order_sync_customer_ledger($conn, (int) $sale_order_id, (int) $new_jwo_id);
} catch (Throwable $e) {
    error_log('auragold_jobwork_order_sync_customer_ledger (insert): ' . $e->getMessage());
}

try {
    require_once __DIR__ . '/../includes/auragold_notifications.php';
    $rjw_new = @getRecord('SELECT jobwork_no, customer_name, order_date, due_date FROM tbl_jobwork_orders WHERE id = ' . (int) $new_jwo_id . ' LIMIT 1');
    if (is_array($rjw_new)) {
        $dued = isset($rjw_new['due_date']) && $rjw_new['due_date'] !== null && trim((string) $rjw_new['due_date']) !== ''
            ? substr(trim((string) $rjw_new['due_date']), 0, 10) : '';
        $od = substr(trim((string) ($rjw_new['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $od)) {
            $od = date('Y-m-d');
        }
        auragold_notify_document_saved($conn, [
            'label' => 'Jobwork Order',
            'verb' => 'created',
            'number' => trim((string) ($rjw_new['jobwork_no'] ?? '')),
            'party' => trim((string) ($rjw_new['customer_name'] ?? '')),
            'doc_date' => $od,
            'due_date' => $dued,
            'ref_id' => (int) $new_jwo_id,
        ]);
    }
} catch (Throwable $e) {
    error_log('auragold_notify_document_saved (jwo insert): ' . $e->getMessage());
}

auragold_jwo_json_echo([
    'status' => 'success',
    'message' => 'Job work order created',
    'jwo_id' => $new_jwo_id,
    'job_work_no' => $jobwork_no,
    'jobwork_no' => $jobwork_no,
    'jwo_status' => $jwo_status,
    'sale_order_id' => $sale_order_id,
    'new_barcodes' => $metal_exchange_barcodes_out,
]);
