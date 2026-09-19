<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_ensure_exchange_rate_column.php';

require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$jwi_pay_raw = $_POST['payments'] ?? '';
$jwi_payments_arr = [];
if (is_string($jwi_pay_raw) && trim($jwi_pay_raw) !== '') {
    $jwi_payments_arr = json_decode($jwi_pay_raw, true);
}
if (!is_array($jwi_payments_arr)) {
    $jwi_payments_arr = [];
}
$jwi_payments_arr = array_values(array_filter($jwi_payments_arr, function ($pay) use ($conn) {
    if (!is_array($pay)) {
        return false;
    }
    if (!empty($pay['readonly_from_sale_order'])) {
        return false;
    }
    if (function_exists('auragold_payment_merge_stored_details')
        && function_exists('auragold_payment_is_metal_exchange_inward')) {
        $merged = auragold_payment_merge_stored_details($pay);
        if (auragold_payment_is_metal_exchange_inward($conn, $merged)) {
            return false;
        }
    }

    return true;
}));
$metal_exchange_barcodes_out = [];
$stock_posted_barcodes_out = [];

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_invoices'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) {
        mysqli_free_result($tbl);
    }
    echo json_encode(['status' => 'error', 'message' => 'Please run admin/sql/create_tbl_jobwork_invoices.sql']);
    exit;
}
mysqli_free_result($tbl);

/**
 * Allow invoices linked to tbl_repair_jobwork_orders (RJWO): nullable jobwork_order_id + repair_jobwork_order_id.
 */
function auragold_jobwork_invoices_ensure_repair_schema(mysqli $conn): void
{
    $cd = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_invoices LIKE 'repair_jobwork_order_id'");
    if (!$cd || mysqli_num_rows($cd) === 0) {
        if ($cd) {
            mysqli_free_result($cd);
        }
        @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_invoices ADD COLUMN repair_jobwork_order_id INT NULL DEFAULT NULL AFTER jobwork_order_id');
    } elseif ($cd) {
        mysqli_free_result($cd);
    }
    @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_invoices MODIFY jobwork_order_id INT NULL DEFAULT NULL');
    $idx = @mysqli_query($conn, "SHOW INDEX FROM tbl_jobwork_invoices WHERE Key_name = 'uniq_jobwork_order'");
    if ($idx && mysqli_num_rows($idx) > 0) {
        mysqli_free_result($idx);
        @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_invoices DROP INDEX uniq_jobwork_order');
    } elseif ($idx) {
        mysqli_free_result($idx);
    }
    $idx2 = @mysqli_query($conn, "SHOW INDEX FROM tbl_jobwork_invoices WHERE Key_name = 'uniq_repair_jwo'");
    if (!$idx2 || mysqli_num_rows($idx2) === 0) {
        if ($idx2) {
            mysqli_free_result($idx2);
        }
        @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_invoices ADD UNIQUE KEY uniq_repair_jwo (repair_jobwork_order_id)');
    } elseif ($idx2) {
        mysqli_free_result($idx2);
    }
    $idx3 = @mysqli_query($conn, "SHOW INDEX FROM tbl_jobwork_invoices WHERE Key_name = 'uniq_jwo_id'");
    if (!$idx3 || mysqli_num_rows($idx3) === 0) {
        if ($idx3) {
            mysqli_free_result($idx3);
        }
        @mysqli_query($conn, 'ALTER TABLE tbl_jobwork_invoices ADD UNIQUE KEY uniq_jwo_id (jobwork_order_id)');
    } elseif ($idx3) {
        mysqli_free_result($idx3);
    }
}

auragold_jobwork_invoices_ensure_repair_schema($conn);

$repair_jobwork_order_id = isset($_POST['repair_jobwork_order_id']) ? (int) $_POST['repair_jobwork_order_id'] : 0;
$jobwork_order_id = isset($_POST['jobwork_order_id']) ? (int) $_POST['jobwork_order_id'] : 0;
$invoice_no = isset($_POST['invoice_no']) ? trim((string) $_POST['invoice_no']) : '';
$grand_total = isset($_POST['grand_total']) ? (float) $_POST['grand_total'] : 0;
$customer_name = isset($_POST['customer_name']) ? trim((string) $_POST['customer_name']) : '';

if ($repair_jobwork_order_id > 0) {
    $rjwo = getRecord("SELECT id, repair_order_id, customer_name FROM tbl_repair_jobwork_orders WHERE id = $repair_jobwork_order_id LIMIT 1");
    if (!$rjwo) {
        echo json_encode(['status' => 'error', 'message' => 'Repair job work order not found']);
        exit;
    }
    if ($customer_name === '') {
        $customer_name = (string) ($rjwo['customer_name'] ?? '');
    }
    if ($invoice_no === '') {
        $cfg = function_exists('getJobworkInvoiceBillSeriesConfig')
            ? getJobworkInvoiceBillSeriesConfig($conn)
            : ['prefix' => 'JWI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
        $invoice_no = function_exists('getNextJobworkInvoiceNo') ? getNextJobworkInvoiceNo($conn) : 'JWI-1';
        $jwi_active = auragold_doc_series_active_sql($conn, 'tbl_jobwork_invoices');
        $existing = getRecord("SELECT id FROM tbl_jobwork_invoices WHERE invoice_no = '" . mysqli_real_escape_string($conn, $invoice_no) . "'" . $jwi_active . " LIMIT 1");
        $guard = 0;
        while ($existing && $guard < 5000) {
            if (function_exists('bumpJobworkInvoiceNo')) {
                $invoice_no = bumpJobworkInvoiceNo($conn, $invoice_no, $cfg);
            } else {
                $invoice_no .= '-1';
            }
            $existing = getRecord("SELECT id FROM tbl_jobwork_invoices WHERE invoice_no = '" . mysqli_real_escape_string($conn, $invoice_no) . "'" . $jwi_active . " LIMIT 1");
            $guard++;
        }
        if (function_exists('auragold_release_soft_deleted_document_no')) {
            auragold_release_soft_deleted_document_no($conn, [['tbl_jobwork_invoices', 'invoice_no']], $invoice_no);
        }
    }
    $invoice_esc = mysqli_real_escape_string($conn, $invoice_no);
    $cust_esc = mysqli_real_escape_string($conn, $customer_name);
    $rid = (int) $repair_jobwork_order_id;
    $saved = getRecord("SELECT id, invoice_no FROM tbl_jobwork_invoices WHERE repair_jobwork_order_id = $rid LIMIT 1");
    $jwi_repair_row_existed = ($saved && !empty($saved['id']));
    $newId = 0;
    if ($saved && !empty($saved['id'])) {
        $newId = (int) $saved['id'];
        $sql = "UPDATE tbl_jobwork_invoices SET invoice_no = '$invoice_esc', customer_name = '$cust_esc', grand_total = $grand_total, jobwork_order_id = NULL, sale_order_id = NULL, repair_jobwork_order_id = $rid, updated_at = NOW() WHERE id = $newId";
    } else {
        $sql = "INSERT INTO tbl_jobwork_invoices (invoice_no, jobwork_order_id, repair_jobwork_order_id, sale_order_id, customer_name, grand_total, created_at) VALUES ('$invoice_esc', NULL, $rid, NULL, '$cust_esc', $grand_total, NOW())";
    }
    mysqli_begin_transaction($conn);
    try {
        if (!mysqli_query($conn, $sql)) {
            throw new RuntimeException(mysqli_error($conn));
        }
        if ($newId <= 0) {
            $newId = (int) mysqli_insert_id($conn);
        }
        require_once dirname(__DIR__) . '/includes/jobwork_invoice_stock_in.php';
        auragold_jobwork_invoice_apply_stock_in($conn, $newId, 0, $invoice_no, $rid, $stock_posted_barcodes_out);
        auragold_jobwork_invoice_apply_gem_stock_in($conn, $newId, $invoice_no, 0);
        if (!mysqli_query($conn, "UPDATE tbl_repair_jobwork_orders SET status = 'Completed' WHERE id = $rid")) {
            throw new RuntimeException(mysqli_error($conn));
        }
        $repair_order_id = (int) ($rjwo['repair_order_id'] ?? 0);
        if ($repair_order_id > 0) {
            if (!mysqli_query($conn, 'UPDATE tbl_repair_orders SET status = \'completed\', updated_at = NOW() WHERE id = ' . $repair_order_id)) {
                throw new RuntimeException(mysqli_error($conn));
            }
        }
        if (!empty($jwi_payments_arr)) {
            foreach ($jwi_payments_arr as $__jp0) {
                if (!is_array($__jp0)) {
                    continue;
                }
                $__mj = auragold_payment_merge_stored_details($__jp0);
                if (!auragold_payment_is_metal_exchange_inward($conn, $__mj)) {
                    continue;
                }
                auragold_validate_metal_exchange_for_stock($conn, $__mj);
            }
            $___jwir_me_ref = auragold_metal_exchange_document_init($conn, $jwi_repair_row_existed, (int) $newId, 'jobwork_invoice_metal_exchange');
            $__jwi_dt_j = substr(trim((string) date('Y-m-d')), 0, 10);
            foreach ($jwi_payments_arr as $pay_seq => $__pay) {
                if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $__pay)) {
                    continue;
                }
                $___pm_jwi_r = auragold_payment_merge_stored_details($__pay);
                auragold_post_metal_exchange_payment_to_stock(
                    $conn,
                    'jobwork_invoice_metal_exchange',
                    (int) $newId,
                    trim((string) $invoice_no),
                    $__jwi_dt_j,
                    $___pm_jwi_r,
                    auragold_metal_exchange_default_branch_id(),
                    is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                    $___jwir_me_ref,
                    'Jobwork Invoice — Metal Exchange',
                    'jwi_me',
                    'JWI-ME-',
                    $metal_exchange_barcodes_out
                );
            }
        }
        mysqli_commit($conn);
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    require_once dirname(__DIR__) . '/includes/auragold_notifications.php';
    auragold_notify_document_saved($conn, [
        'label' => 'Jobwork Invoice',
        'verb' => $jwi_repair_row_existed ? 'updated' : 'created',
        'number' => $invoice_no,
        'party' => $customer_name,
        'doc_date' => date('Y-m-d'),
        'due_date' => '',
        'ref_id' => (int) $newId,
    ]);
    echo json_encode([
        'status' => 'success',
        'message' => 'Jobwork invoice saved',
        'id' => (int) $newId,
        'invoice_no' => $invoice_no,
        'repair_jobwork_order_id' => $rid,
        'jobwork_order_id' => 0,
        'new_barcodes' => $metal_exchange_barcodes_out,
        'stock_posted_barcodes' => $stock_posted_barcodes_out,
    ]);
    exit;
}

if ($jobwork_order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Job work order ID required']);
    exit;
}

$jwo = getRecord("SELECT id, sale_order_id, customer_name FROM tbl_jobwork_orders WHERE id = $jobwork_order_id LIMIT 1");
if (!$jwo) {
    echo json_encode(['status' => 'error', 'message' => 'Job work order not found']);
    exit;
}

if ($invoice_no === '') {
    $cfg = function_exists('getJobworkInvoiceBillSeriesConfig')
        ? getJobworkInvoiceBillSeriesConfig($conn)
        : ['prefix' => 'JWI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $invoice_no = function_exists('getNextJobworkInvoiceNo') ? getNextJobworkInvoiceNo($conn) : 'JWI-1';
    $jwi_active = auragold_doc_series_active_sql($conn, 'tbl_jobwork_invoices');
    $existing = getRecord("SELECT id FROM tbl_jobwork_invoices WHERE invoice_no = '" . mysqli_real_escape_string($conn, $invoice_no) . "'" . $jwi_active . " LIMIT 1");
    $guard = 0;
    while ($existing && $guard < 5000) {
        if (function_exists('bumpJobworkInvoiceNo')) {
            $invoice_no = bumpJobworkInvoiceNo($conn, $invoice_no, $cfg);
        } else {
            $invoice_no .= '-1';
        }
        $existing = getRecord("SELECT id FROM tbl_jobwork_invoices WHERE invoice_no = '" . mysqli_real_escape_string($conn, $invoice_no) . "'" . $jwi_active . " LIMIT 1");
        $guard++;
    }
    if (function_exists('auragold_release_soft_deleted_document_no')) {
        auragold_release_soft_deleted_document_no($conn, [['tbl_jobwork_invoices', 'invoice_no']], $invoice_no);
    }
}

if ($customer_name === '') {
    $customer_name = (string) ($jwo['customer_name'] ?? '');
}

$invoice_esc = mysqli_real_escape_string($conn, $invoice_no);
$cust_esc = mysqli_real_escape_string($conn, $customer_name);
$sale_order_id = isset($jwo['sale_order_id']) ? (int) $jwo['sale_order_id'] : 0;

$oj_refinery_skip_main_stock = false;
$oj_refinery_needle = 'Job work / refinery from Old Jewellery scrap';
if ($sale_order_id > 0) {
    $so_row = getRecord('SELECT comment FROM tbl_sale_orders WHERE id = ' . $sale_order_id . ' LIMIT 1');
    if ($so_row && stripos((string) ($so_row['comment'] ?? ''), $oj_refinery_needle) !== false) {
        $oj_refinery_skip_main_stock = true;
    }
}

$saved = getRecord("SELECT id, invoice_no FROM tbl_jobwork_invoices WHERE jobwork_order_id = $jobwork_order_id LIMIT 1");
$jwi_so_row_existed = ($saved && !empty($saved['id']));
$newId = 0;
if ($saved && !empty($saved['id'])) {
    $newId = (int) $saved['id'];
    $sql = "UPDATE tbl_jobwork_invoices SET invoice_no = '$invoice_esc', customer_name = '$cust_esc', grand_total = $grand_total, sale_order_id = " . ($sale_order_id > 0 ? $sale_order_id : 'NULL') . ", repair_jobwork_order_id = NULL, updated_at = NOW() WHERE id = $newId";
} else {
    $sql = "INSERT INTO tbl_jobwork_invoices (invoice_no, jobwork_order_id, repair_jobwork_order_id, sale_order_id, customer_name, grand_total, created_at) VALUES ('$invoice_esc', $jobwork_order_id, NULL, " . ($sale_order_id > 0 ? $sale_order_id : 'NULL') . ", '$cust_esc', $grand_total, NOW())";
}

$jid = (int) $jobwork_order_id;

mysqli_begin_transaction($conn);
try {
    if (!mysqli_query($conn, $sql)) {
        throw new RuntimeException(mysqli_error($conn));
    }
    if ($newId <= 0) {
        $newId = (int) mysqli_insert_id($conn);
    }

    require_once dirname(__DIR__) . '/includes/jobwork_invoice_stock_in.php';
    if (!$oj_refinery_skip_main_stock) {
        auragold_jobwork_invoice_apply_stock_in($conn, $newId, $jobwork_order_id, $invoice_no, 0, $stock_posted_barcodes_out);
        auragold_jobwork_invoice_apply_gem_stock_in($conn, $newId, $invoice_no, $sale_order_id);
    }

    if ($jid > 0) {
        if (!mysqli_query($conn, "UPDATE tbl_jobwork_orders SET status = 'Completed' WHERE id = $jid")) {
            throw new RuntimeException(mysqli_error($conn));
        }
    }
    if ($sale_order_id > 0 && !$oj_refinery_skip_main_stock) {
        if (!mysqli_query($conn, "UPDATE tbl_sale_orders SET status = 'completed' WHERE id = " . (int) $sale_order_id)) {
            throw new RuntimeException(mysqli_error($conn));
        }
    }

    if (!empty($jwi_payments_arr)) {
        foreach ($jwi_payments_arr as $__jp1) {
            if (!is_array($__jp1)) {
                continue;
            }
            $__mj1 = auragold_payment_merge_stored_details($__jp1);
            if (!auragold_payment_is_metal_exchange_inward($conn, $__mj1)) {
                continue;
            }
            auragold_validate_metal_exchange_for_stock($conn, $__mj1);
        }
        $___jwiso_me_ref = auragold_metal_exchange_document_init($conn, $jwi_so_row_existed, (int) $newId, 'jobwork_invoice_metal_exchange');
        $__jwi_dt_so = substr(trim((string) date('Y-m-d')), 0, 10);
        foreach ($jwi_payments_arr as $pay_seq => $__pay_so) {
            if (!auragold_should_persist_payment_row_with_metal_exchange($conn, $__pay_so)) {
                continue;
            }
            $___pm_jwi_so = auragold_payment_merge_stored_details($__pay_so);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'jobwork_invoice_metal_exchange',
                (int) $newId,
                trim((string) $invoice_no),
                $__jwi_dt_so,
                $___pm_jwi_so,
                auragold_metal_exchange_default_branch_id(),
                is_int($pay_seq) ? $pay_seq : (int) $pay_seq,
                $___jwiso_me_ref,
                'Jobwork Invoice — Metal Exchange',
                'jwi_me',
                'JWI-ME-',
                $metal_exchange_barcodes_out
            );
        }
    }

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

require_once dirname(__DIR__) . '/includes/auragold_notifications.php';
auragold_notify_document_saved($conn, [
    'label' => 'Jobwork Invoice',
    'verb' => $jwi_so_row_existed ? 'updated' : 'created',
    'number' => $invoice_no,
    'party' => $customer_name,
    'doc_date' => date('Y-m-d'),
    'due_date' => '',
    'ref_id' => (int) $newId,
]);

echo json_encode([
    'status' => 'success',
    'message' => 'Jobwork invoice saved',
    'id' => (int) $newId,
    'invoice_no' => $invoice_no,
    'jobwork_order_id' => $jobwork_order_id,
    'new_barcodes' => $metal_exchange_barcodes_out,
    'stock_posted_barcodes' => $stock_posted_barcodes_out,
]);
